<?php

namespace App\Services;

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Support\CustomerRequests\CustomerRequestMessages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerRequestService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'customer_id',
        'request_text',
        'status',
        'source',
        'category_id',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public RequestImageService $requestImageService,
        public RequestMatchingService $requestMatchingService,
        public CustomerRequestLimitService $customerRequestLimitService,
        public CustomerContactAbuseService $customerContactAbuseService,
        public CustomerExtraRequestService $customerExtraRequestService,
    ) {}

    public function getPaginatedRequests(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        ?string $customerPublicId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'status', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return CustomerRequest::query()
            ->with(['customer:id,public_id,name,phone,email', 'category:id,public_id,name_ar,name_en', 'image', 'merchantOffers.merchant:id,name', 'merchantOffers.images', 'latestClassification.suggestedCategory:id,public_id,name_ar,name_en', 'latestClassification.confirmedCategory:id,public_id,name_ar,name_en'])
            ->withCount(['matches', 'submittedOffers'])
            ->when($customerPublicId, function ($q) use ($customerPublicId) {
                $q->whereHas('customer', fn ($customer) => $customer->where('public_id', $customerPublicId));
            })
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('request_text', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customer) use ($search) {
                        $customer->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?UploadedFile $image = null): CustomerRequest
    {
        $customer = $this->resolveCustomer($data['customer_id'] ?? null, requireActive: true);
        $categoryId = $this->resolveCategoryId($data['category_id'] ?? null);

        return DB::transaction(function () use ($data, $image, $customer, $categoryId) {
            $request = new CustomerRequest;
            $request->public_id = (string) Str::ulid();
            $request->customer_id = $customer->id;
            $request->source = Source::Admin;
            $request->fill(Arr::only($data, ['request_text', 'status']));
            $request->category_id = $categoryId;
            $request->save();

            $this->activityLogService->recordCreated(
                subject: $request,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $customer->display_name,
            );

            if ($image) {
                $this->requestImageService->store($request, $image);
                $this->activityLogService->recordCreated(
                    subject: $request->fresh()->image,
                    allowedFields: ['original_name', 'mime_type', 'size'],
                    subjectLabel: $customer->display_name,
                );
            }

            $this->requestMatchingService->sync($request);

            return $request->fresh(['customer', 'category', 'image']);
        });
    }

    /**
     * Customer self-service request creation.
     * Source and customer ownership are forced server-side.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeForCustomer(Customer $customer, array $data, ?UploadedFile $image = null): CustomerRequest
    {
        $this->customerContactAbuseService->assertCanCreate($customer);

        if (array_key_exists('category_id', $data) && $data['category_id'] !== null && $data['category_id'] !== '') {
            throw ValidationException::withMessages([
                'category_id' => CustomerRequestMessages::categoryManualProhibited(),
            ]);
        }

        throw ValidationException::withMessages([
            'request_text' => CustomerRequestMessages::categoryManualProhibited(),
        ]);
    }

    /**
     * Draft request used only for AI assistance. Matching must not run.
     *
     * @param  array<string, mixed>  $data
     */
    public function storePendingForCustomer(Customer $customer, array $data, ?UploadedFile $image = null): CustomerRequest
    {
        $this->customerContactAbuseService->assertCanCreate($customer);

        return DB::transaction(function () use ($customer, $data, $image) {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
            if ($locked === null) {
                abort(404);
            }

            $this->customerContactAbuseService->assertCanCreate($locked);
            $dailyQuotaExhausted = $this->customerRequestLimitService->dailyQuotaExhausted($locked);
            $this->customerRequestLimitService->assertWithinLimit($locked);

            $request = new CustomerRequest;
            $request->public_id = (string) Str::ulid();
            $request->customer_id = $locked->id;
            $request->source = Source::Web;
            $request->status = RequestStatus::PendingClassification;
            $request->request_text = (string) $data['request_text'];
            $request->category_id = null;
            $request->save();

            if ($dailyQuotaExhausted) {
                $this->customerExtraRequestService->consumeForNewRequest($locked, $request);
            }

            $this->activityLogService->recordCreated(
                subject: $request,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $customer->display_name,
                metadata: [
                    'action' => 'customer.request_pending_classification',
                    'customer_id' => $customer->id,
                    'user_id' => $customer->user_id,
                ],
            );

            if ($image) {
                $this->requestImageService->store($request, $image);
            }

            return $request->fresh(['image']);
        });
    }

    public function appendDetailsAndMaybeReplaceImage(
        CustomerRequest $customerRequest,
        ?string $additionalDetails,
        ?UploadedFile $image = null
    ): CustomerRequest {
        if (is_string($additionalDetails) && trim($additionalDetails) !== '') {
            $customerRequest->request_text = trim($customerRequest->request_text."\n".$additionalDetails);
            $customerRequest->save();
        }

        if ($image) {
            $this->requestImageService->store($customerRequest, $image);
        }

        return $customerRequest->fresh(['image']);
    }

    public function finalizeReady(CustomerRequest $customerRequest, int $categoryId): CustomerRequest
    {
        $customerRequest->category_id = $categoryId;
        $customerRequest->status = RequestStatus::Ready;
        $customerRequest->save();
        $customerRequest->unsetRelation('category');

        $this->requestMatchingService->sync($customerRequest);

        return $customerRequest->fresh(['category', 'image']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerRequest $customerRequest, array $data, ?UploadedFile $image = null, bool $removeImage = false): CustomerRequest
    {
        $customer = $this->resolveCustomer($data['customer_id'] ?? $customerRequest->customer->public_id, requireActive: false);
        $categoryId = $this->resolveCategoryId($data['category_id'] ?? null);

        $originalValues = $customerRequest->only(self::ACTIVITY_FIELDS);
        $originalCategoryId = $customerRequest->category_id;
        $originalStatus = $customerRequest->status;
        $hadImage = $customerRequest->image !== null;

        return DB::transaction(function () use ($customerRequest, $data, $image, $removeImage, $customer, $categoryId, $originalValues, $originalCategoryId, $originalStatus, $hadImage) {
            $customerRequest->customer_id = $customer->id;
            $customerRequest->fill(Arr::only($data, ['request_text', 'status']));
            $customerRequest->category_id = $categoryId;
            $customerRequest->save();

            $this->activityLogService->recordChanges(
                subject: $customerRequest,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $customer->display_name,
            );

            if ($image) {
                $this->requestImageService->store($customerRequest, $image);
                $this->activityLogService->recordCreated(
                    subject: $customerRequest->fresh()->image,
                    allowedFields: ['original_name', 'mime_type', 'size'],
                    subjectLabel: $hadImage ? 'replaced' : 'added',
                );
            } elseif ($removeImage && $hadImage) {
                $this->requestImageService->delete($customerRequest);
                $this->activityLogService->recordDeleted(
                    subject: $customerRequest,
                    allowedFields: ['status'],
                    subjectLabel: 'image-removed',
                    metadata: ['image' => 'removed'],
                );
            }

            if ($customerRequest->category_id !== $originalCategoryId || $customerRequest->status !== $originalStatus) {
                $this->requestMatchingService->sync($customerRequest);
            }

            return $customerRequest->fresh(['customer', 'category', 'image']);
        });
    }

    private function resolveCustomer(mixed $publicId, bool $requireActive): Customer
    {
        if (! is_string($publicId) || $publicId === '') {
            throw ValidationException::withMessages([
                'customer_id' => 'The selected customer is invalid.',
            ]);
        }

        $customer = Customer::query()->where('public_id', $publicId)->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'The selected customer is invalid.',
            ]);
        }

        if ($requireActive && $customer->status !== CustomerStatus::Active) {
            throw ValidationException::withMessages([
                'customer_id' => 'Inactive customers cannot receive new requests.',
            ]);
        }

        return $customer;
    }

    private function resolveCategoryId(mixed $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        if (! is_string($publicId)) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is invalid.',
            ]);
        }

        $category = Category::query()
            ->where('public_id', $publicId)
            ->where('status', CategoryStatus::Active)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is invalid or inactive.',
            ]);
        }

        return $category->id;
    }
}
