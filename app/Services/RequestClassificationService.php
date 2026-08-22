<?php

namespace App\Services;

use App\Contracts\AiClassificationProviderInterface;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\RequestImage;
use App\Support\Classification\ClassificationCandidate;
use App\Support\Classification\ClassificationInput;
use App\Support\Classification\ClassificationResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RequestClassificationService
{
    public function __construct(
        public AiClassificationProviderInterface $provider,
        public CustomerRequestService $customerRequestService,
        public CategoryService $categoryService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function classify(Customer $customer, array $data, ?UploadedFile $image = null): RequestClassification
    {
        $request = $this->resolvePendingRequest($customer, $data, $image);
        $input = $this->buildInput($request);

        try {
            $raw = $this->provider->classify($input);
            $sanitized = $this->sanitizeResult($raw);
            $status = $this->statusFor($sanitized);

            return $this->storeAttempt($request, $sanitized, $status, $input->hasImage);
        } catch (Throwable $exception) {
            report($exception);

            return $this->storeAttempt(
                $request,
                new ClassificationResult(
                    detectedItem: null,
                    confidence: null,
                    primaryCategoryPublicId: null,
                    alternatives: [],
                    needsMoreInformation: false,
                    question: null,
                    reason: 'provider-failed',
                ),
                ClassificationStatus::Failed,
                $input->hasImage,
            );
        }
    }

    public function confirm(Customer $customer, RequestClassification $classification, string $categoryPublicId, ?CustomerRequest $boundRequest = null): CustomerRequest
    {
        $request = $classification->customerRequest()->first();

        if ($request === null || (int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($boundRequest !== null && (int) $boundRequest->id !== (int) $request->id) {
            abort(404);
        }

        $this->assertPendingClassification($request);

        $category = $this->requireActiveCategory($categoryPublicId);

        $classification->status = ClassificationStatus::Confirmed;
        $classification->customer_confirmed_category_id = $category->id;
        $classification->confirmed_at = now();
        $classification->save();

        return $this->customerRequestService->finalizeReady($request, $category->id);
    }

    public function finalizeWithCategory(Customer $customer, CustomerRequest $request, string $categoryPublicId): CustomerRequest
    {
        if ((int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $this->assertPendingClassification($request);

        $category = $this->requireActiveCategory($categoryPublicId);

        $latest = $request->latestClassification()->first();
        if ($latest instanceof RequestClassification) {
            $latest->status = ClassificationStatus::Confirmed;
            $latest->customer_confirmed_category_id = $category->id;
            $latest->confirmed_at = now();
            $latest->save();
        }

        return $this->customerRequestService->finalizeReady($request, $category->id);
    }

    /**
     * Retry classification against an existing pending request. Does not create a new customer_request.
     *
     * @param  array<string, mixed>  $data
     */
    public function retry(Customer $customer, CustomerRequest $request, array $data, ?UploadedFile $image = null): RequestClassification
    {
        if ((int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $this->assertPendingClassification($request);

        return $this->classify($customer, [
            'request_text' => $request->request_text,
            'additional_details' => $data['additional_details'] ?? null,
            'pending_request_id' => $request->public_id,
        ], $image);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForCustomer(RequestClassification $classification): array
    {
        $classification->loadMissing(['suggestedCategory:id,public_id,name_ar,name_en,status', 'customerRequest:id,public_id,status']);

        $band = $this->confidenceBand($classification->confidence);
        $suggestions = $this->presentSuggestions($classification);

        $suggested = $classification->suggestedCategory;
        $suggestedIsActive = $suggested !== null && $suggested->status === CategoryStatus::Active;
        $requestIsPending = $classification->customerRequest?->status === RequestStatus::PendingClassification;

        return [
            'public_id' => $classification->public_id,
            'request_public_id' => $classification->customerRequest?->public_id,
            'status' => $classification->status?->name,
            'status_formatted' => $classification->status_formatted,
            'detected_item' => $classification->detected_item,
            'confidence' => $classification->confidence,
            'confidence_band' => $band,
            'needs_more_information' => (bool) $classification->needs_more_information,
            'question' => $classification->question,
            'reason' => $classification->reason,
            'failed' => $classification->status === ClassificationStatus::Failed,
            'primary' => $suggestions[0] ?? null,
            'suggestions' => $suggestions,
            'suggested_category' => $suggestedIsActive && $suggested !== null ? [
                'public_id' => $suggested->public_id,
                'name_ar' => $suggested->name_ar,
                'name_en' => $suggested->name_en,
            ] : null,
            'suggested_category_inactive' => $suggested !== null && ! $suggestedIsActive,
            'can_confirm' => $requestIsPending
                && $classification->status !== ClassificationStatus::Failed
                && $suggestions !== [],
        ];
    }

    /**
     * Latest classification for an owned pending request, or null when not resumable.
     *
     * @return array<string, mixed>|null
     */
    public function presentLatestForPendingRequest(CustomerRequest $request): ?array
    {
        if ($request->status !== RequestStatus::PendingClassification) {
            return null;
        }

        $latest = $request->latestClassification()->first();

        if ($latest === null) {
            return [
                'public_id' => null,
                'request_public_id' => $request->public_id,
                'status' => null,
                'status_formatted' => null,
                'detected_item' => null,
                'confidence' => null,
                'confidence_band' => 'low',
                'needs_more_information' => false,
                'question' => null,
                'reason' => null,
                'failed' => false,
                'primary' => null,
                'suggestions' => [],
                'suggested_category' => null,
                'suggested_category_inactive' => false,
                'can_confirm' => false,
            ];
        }

        return $this->presentForCustomer($latest);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForAdmin(?RequestClassification $classification): ?array
    {
        if ($classification === null) {
            return null;
        }

        $classification->loadMissing([
            'suggestedCategory:id,public_id,name_ar,name_en',
            'confirmedCategory:id,public_id,name_ar,name_en',
        ]);

        return [
            'public_id' => $classification->public_id,
            'provider' => $classification->provider,
            'model' => $classification->model,
            'detected_item' => $classification->detected_item,
            'confidence' => $classification->confidence,
            'status' => $classification->status?->name,
            'status_formatted' => $classification->status_formatted,
            'suggested_category' => $classification->suggestedCategory ? [
                'public_id' => $classification->suggestedCategory->public_id,
                'name_ar' => $classification->suggestedCategory->name_ar,
                'name_en' => $classification->suggestedCategory->name_en,
            ] : null,
            'confirmed_category' => $classification->confirmedCategory ? [
                'public_id' => $classification->confirmedCategory->public_id,
                'name_ar' => $classification->confirmedCategory->name_ar,
                'name_en' => $classification->confirmedCategory->name_en,
            ] : null,
            'created_at' => $classification->created_at,
            'confirmed_at' => $classification->confirmed_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePendingRequest(Customer $customer, array $data, ?UploadedFile $image): CustomerRequest
    {
        $pendingPublicId = $data['pending_request_id'] ?? null;

        if (is_string($pendingPublicId) && $pendingPublicId !== '') {
            $existing = CustomerRequest::query()->where('public_id', $pendingPublicId)->first();

            if ($existing === null || (int) $existing->customer_id !== (int) $customer->id) {
                abort(404);
            }

            if ($existing->status !== RequestStatus::PendingClassification) {
                throw ValidationException::withMessages([
                    'request_text' => 'This request can no longer be classified.',
                ]);
            }

            return $this->customerRequestService->appendDetailsAndMaybeReplaceImage(
                $existing,
                isset($data['additional_details']) ? (string) $data['additional_details'] : null,
                $image,
            );
        }

        return $this->customerRequestService->storePendingForCustomer($customer, $data, $image);
    }

    private function buildInput(CustomerRequest $request): ClassificationInput
    {
        $request->loadMissing('image');
        $image = $request->image;
        $contents = null;
        $mime = null;
        $size = null;
        $hasImage = false;

        if ($image instanceof RequestImage && is_string($image->path) && $image->path !== '') {
            $disk = Storage::disk(RequestImage::DISK);
            if ($disk->exists($image->path)) {
                $hasImage = true;
                $mime = $image->mime_type;
                $size = $image->size;
                $contents = $disk->get($image->path);
            }
        }

        return new ClassificationInput(
            requestText: $request->request_text,
            hasImage: $hasImage,
            imageMime: $mime,
            imageSize: $size,
            imageContents: $contents,
            taxonomy: $this->taxonomyForProvider(),
        );
    }

    /**
     * @return list<array{public_id: string, name_ar: string, name_en: string, parent_public_id: ?string, parent_name_ar: ?string, parent_name_en: ?string}>
     */
    private function taxonomyForProvider(): array
    {
        $categories = $this->categoryService->activeCategoriesForAssignment()->load('parent:id,public_id,name_ar,name_en');

        return $categories->map(fn (Category $category) => [
            'public_id' => $category->public_id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'parent_public_id' => $category->parent?->public_id,
            'parent_name_ar' => $category->parent?->name_ar,
            'parent_name_en' => $category->parent?->name_en,
        ])->values()->all();
    }

    private function sanitizeResult(ClassificationResult $result): ClassificationResult
    {
        $primary = $this->activeCategoryPublicId($result->primaryCategoryPublicId);
        $alternatives = [];

        foreach ($result->alternatives as $candidate) {
            if (! $candidate instanceof ClassificationCandidate) {
                continue;
            }

            $publicId = $this->activeCategoryPublicId($candidate->categoryPublicId);
            if ($publicId === null) {
                continue;
            }

            $alternatives[] = new ClassificationCandidate($publicId, $this->clampConfidence($candidate->confidence));
        }

        if ($primary !== null && ! collect($alternatives)->contains(fn (ClassificationCandidate $row) => $row->categoryPublicId === $primary)) {
            array_unshift($alternatives, new ClassificationCandidate($primary, $this->clampConfidence($result->confidence ?? 0)));
        }

        $alternatives = array_slice($alternatives, 0, 3);

        return new ClassificationResult(
            detectedItem: $result->detectedItem,
            confidence: $this->clampConfidence($result->confidence),
            primaryCategoryPublicId: $primary,
            alternatives: $alternatives,
            needsMoreInformation: $result->needsMoreInformation || ($this->confidenceBand($result->confidence) === 'low'),
            question: $result->question,
            reason: $result->reason,
            usage: $result->usage,
        );
    }

    private function activeCategoryPublicId(?string $publicId): ?string
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        $exists = Category::query()
            ->where('public_id', $publicId)
            ->where('status', CategoryStatus::Active)
            ->exists();

        return $exists ? $publicId : null;
    }

    private function clampConfidence(?float $confidence): ?float
    {
        if ($confidence === null) {
            return null;
        }

        return max(0, min(1, round($confidence, 4)));
    }

    private function statusFor(ClassificationResult $result): ClassificationStatus
    {
        if ($result->needsMoreInformation || $this->confidenceBand($result->confidence) === 'low' || $result->primaryCategoryPublicId === null) {
            return ClassificationStatus::NeedsReview;
        }

        return ClassificationStatus::Suggested;
    }

    private function confidenceBand(?float $confidence): string
    {
        $high = (float) config('classification.high_confidence', 0.85);
        $medium = (float) config('classification.medium_confidence', 0.60);

        if ($confidence === null) {
            return 'low';
        }

        if ($confidence >= $high) {
            return 'high';
        }

        if ($confidence >= $medium) {
            return 'medium';
        }

        return 'low';
    }

    private function storeAttempt(
        CustomerRequest $request,
        ClassificationResult $result,
        ClassificationStatus $status,
        bool $hasImage
    ): RequestClassification {
        $suggestedId = null;
        if (is_string($result->primaryCategoryPublicId)) {
            $suggestedId = Category::query()->where('public_id', $result->primaryCategoryPublicId)->value('id');
        }

        $row = new RequestClassification;
        $row->public_id = (string) Str::ulid();
        $row->customer_request_id = $request->id;
        $row->provider = (string) config('classification.provider', 'fake');
        $row->model = config('classification.model');
        $row->detected_item = $result->detectedItem;
        $row->suggested_category_id = $suggestedId;
        $row->confidence = $result->confidence;
        $row->alternatives = array_map(fn (ClassificationCandidate $candidate) => $candidate->toArray(), $result->alternatives);
        $row->needs_more_information = $result->needsMoreInformation;
        $row->question = $result->question;
        $row->reason = $result->reason;
        $row->status = $status;
        $row->input_has_image = $hasImage;
        $row->provider_response_id = $result->usage?->responseId;
        $row->input_tokens = $result->usage?->inputTokens;
        $row->cached_input_tokens = $result->usage?->cachedInputTokens;
        $row->output_tokens = $result->usage?->outputTokens;
        $row->reasoning_tokens = $result->usage?->reasoningTokens;
        $row->total_tokens = $result->usage?->totalTokens;
        $row->save();

        return $row->fresh(['suggestedCategory', 'customerRequest.image']);
    }

    /**
     * @return list<array{category_public_id: string, name_ar: string, name_en: string, confidence: float|null}>
     */
    private function presentSuggestions(RequestClassification $classification): array
    {
        $items = [];
        $seen = [];

        if ($classification->suggestedCategory && $classification->suggestedCategory->status === CategoryStatus::Active) {
            $items[] = [
                'category_public_id' => $classification->suggestedCategory->public_id,
                'name_ar' => $classification->suggestedCategory->name_ar,
                'name_en' => $classification->suggestedCategory->name_en,
                'confidence' => $classification->confidence,
            ];
            $seen[$classification->suggestedCategory->public_id] = true;
        }

        foreach ($classification->alternatives ?? [] as $row) {
            $publicId = $row['category_public_id'] ?? null;
            if (! is_string($publicId) || isset($seen[$publicId])) {
                continue;
            }

            $category = Category::query()->where('public_id', $publicId)->where('status', CategoryStatus::Active)->first();
            if ($category === null) {
                continue;
            }

            $items[] = [
                'category_public_id' => $category->public_id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : null,
            ];
            $seen[$publicId] = true;
        }

        return array_slice($items, 0, 3);
    }

    private function assertPendingClassification(CustomerRequest $request): void
    {
        if ($request->status !== RequestStatus::PendingClassification) {
            throw ValidationException::withMessages([
                'category_id' => 'This request can no longer be classified.',
            ]);
        }
    }

    private function requireActiveCategory(string $categoryPublicId): Category
    {
        $category = Category::query()
            ->where('public_id', $categoryPublicId)
            ->where('status', CategoryStatus::Active)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is invalid.',
            ]);
        }

        return $category;
    }
}
