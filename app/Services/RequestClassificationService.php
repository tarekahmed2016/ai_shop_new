<?php

namespace App\Services;

use App\Contracts\AiClassificationProviderInterface;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\IntakeAction;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Exceptions\DuplicateCustomerRequestException;
use App\Jobs\ClassifyCustomerRequestJob;
use App\Jobs\FinalizeCustomerRequestJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\RequestImage;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Services\CustomerRequests\CustomerRequestIdempotencyService;
use App\Support\Classification\ClassificationCandidate;
use App\Support\Classification\ClassificationInput;
use App\Support\Classification\ClassificationResult;
use App\Support\CustomerRequests\CustomerRequestMessages;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use App\Support\CustomerRequests\NormalizedRequestSnapshot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Classification + confirm orchestration.
 *
 * While classification.async_enabled is false, classify()/confirm()/retry()/
 * finalizeWithCategory() remain the live synchronous HTTP path (OpenAI
 * inside the request). When the flag is on, HTTP handlers call the intake*
 * methods instead; those never call an AI provider and only dispatch jobs.
 * runClassificationAttempt() is the async job body.
 */
class RequestClassificationService
{
    public function __construct(
        public AiClassificationProviderInterface $provider,
        public CustomerRequestService $customerRequestService,
        public CategoryService $categoryService,
        public ContactInformationScanner $contactInformationScanner,
        public CustomerContactAbuseService $customerContactAbuseService,
        public CustomerRequestLimitService $customerRequestLimitService,
        public CustomerRequestDuplicateDetectionService $duplicateDetectionService,
        public NormalizedRequestSnapshotService $normalizedRequestSnapshotService,
        public CustomerRequestAiStageService $stageService,
        public CustomerRequestIdempotencyService $idempotency,
    ) {}

    // =====================================================================
    // Legacy synchronous path (classification.async_enabled = false)
    // =====================================================================

    /**
     * @param  array<string, mixed>  $data
     */
    public function classify(Customer $customer, array $data, ?UploadedFile $image = null): RequestClassification
    {
        $this->customerContactAbuseService->assertCanCreate($customer);

        $pending = $this->existingPendingRequest($customer, $data['pending_request_id'] ?? null);
        $text = $this->classificationText($pending, $data);

        $layerOne = $this->contactInformationScanner->scanText($text);
        if ($layerOne->detected) {
            $this->customerContactAbuseService->blockAndSuspend($customer, $layerOne->types);
        }

        if ($pending === null) {
            $this->customerRequestLimitService->assertWithinLimit($customer);
        }

        $input = $this->buildInputFromParts($text, $image, $pending);

        try {
            $raw = $this->provider->classify($input);
            $sanitized = $this->sanitizeResult($raw);

            if ($this->contactInformationScanner->confirmedFromAi($sanitized)) {
                $this->customerContactAbuseService->blockAndSuspend(
                    $customer,
                    $sanitized->contactInformationTypes !== []
                        ? $sanitized->contactInformationTypes
                        : ['phone'],
                );
            }

            $status = $this->statusFor($sanitized);
            $request = $this->persistAfterScan($customer, $pending, $data, $image, $sanitized);

            return $this->storeAttempt($request, $sanitized, $status, $input->hasImage);
        } catch (ValidationException|DuplicateCustomerRequestException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $request = $this->persistAfterScan($customer, $pending, $data, $image, null);

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
        $this->customerContactAbuseService->assertCanCreate($customer);
        $request = $classification->customerRequest()->first();

        if ($request === null || (int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($boundRequest !== null && (int) $boundRequest->id !== (int) $request->id) {
            abort(404);
        }

        $this->assertPendingClassification($request);

        $category = $this->assertConfirmableCategory($classification, $categoryPublicId);

        return $this->duplicateDetectionService->runSerialized($customer, function () use ($customer, $request, $classification, $category) {
            $this->assertNotDuplicateOrDiscard($customer, $request, $classification, $category);

            $classification->status = ClassificationStatus::Confirmed;
            $classification->customer_confirmed_category_id = $category->id;
            $classification->confirmed_at = now();
            $classification->save();

            return $this->customerRequestService->finalizeReady($request, $category->id);
        });
    }

    public function finalizeWithCategory(Customer $customer, CustomerRequest $request, string $categoryPublicId): CustomerRequest
    {
        $this->customerContactAbuseService->assertCanCreate($customer);
        if ((int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $this->assertPendingClassification($request);

        $latest = $request->latestClassification()->first();
        if (! $latest instanceof RequestClassification) {
            throw ValidationException::withMessages([
                'category_id' => CustomerRequestMessages::confirmSuggestedOnly(),
            ]);
        }

        $category = $this->assertConfirmableCategory($latest, $categoryPublicId);

        return $this->duplicateDetectionService->runSerialized($customer, function () use ($customer, $request, $latest, $category) {
            $this->assertNotDuplicateOrDiscard($customer, $request, $latest, $category);

            $latest->status = ClassificationStatus::Confirmed;
            $latest->customer_confirmed_category_id = $category->id;
            $latest->confirmed_at = now();
            $latest->save();

            return $this->customerRequestService->finalizeReady($request, $category->id);
        });
    }

    /**
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

    // =====================================================================
    // Intake (async HTTP request path — zero AI calls)
    // =====================================================================

    /**
     * Start a brand-new async classification pipeline. Idempotent per
     * (customer, submission_token): a repeated POST with the same token
     * returns the same row without creating a second one or dispatching a
     * second job.
     */
    public function intakeClassify(Customer $customer, string $requestText, ?UploadedFile $image, string $submissionToken): CustomerRequest
    {
        $this->customerContactAbuseService->assertCanCreate($customer);

        $text = trim($requestText);
        $layerOne = $this->contactInformationScanner->scanText($text);
        if ($layerOne->detected) {
            $this->customerContactAbuseService->blockAndSuspend($customer, $layerOne->types);
        }

        // IMPORTANT: the per-customer lock below (runSerialized) must be
        // fully released before the classification job is dispatched.
        // With QUEUE_CONNECTION=sync (as in tests), job dispatch runs the
        // job inline, and DetectDuplicateCustomerRequestJob acquires this
        // exact same per-customer lock — re-entering a non-reentrant
        // Cache::lock from the same call stack would deadlock until the
        // lock's wait-timeout. Dispatching only after the lock closure
        // returns keeps this safe under every queue driver.
        $dispatch = null;

        $request = $this->duplicateDetectionService->runSerialized($customer, function () use ($customer, $text, $image, $submissionToken, &$dispatch) {
            $existing = $this->idempotency->findRequest($customer, IntakeAction::Classify, $submissionToken)
                ?? $this->findBySubmissionToken($customer, $submissionToken);
            if ($existing !== null) {
                return $existing;
            }

            $this->customerRequestLimitService->assertOpenAttemptCeilingNotReached($customer);
            $this->customerRequestLimitService->assertNoClassificationInFlight($customer);
            // Advisory fast-fail only — the authoritative check happens
            // once more, atomically, inside FinalizeCustomerRequestJob.
            $this->customerRequestLimitService->assertWithinLimit($customer);

            $jobToken = $this->stageService->newToken();

            try {
                $created = $this->customerRequestService->createProcessingRequest(
                    $customer,
                    $text,
                    $image,
                    $submissionToken,
                    $jobToken,
                );
            } catch (UniqueConstraintViolationException $exception) {
                // Lost a race against an identical concurrent retry of the
                // same token (e.g. two browser tabs). Return the winner.
                return $this->idempotency->findRequest($customer, IntakeAction::Classify, $submissionToken)
                    ?? $this->findBySubmissionToken($customer, $submissionToken)
                    ?? throw $exception;
            }

            $dispatch = ['id' => $created->id, 'token' => $jobToken];

            return $created;
        });

        if ($dispatch !== null) {
            ClassifyCustomerRequestJob::dispatch($dispatch['id'], $dispatch['token'])->afterCommit();
        }

        return $request;
    }

    /**
     * Re-run classification on an existing owned row (customer retry after
     * a failure, or "add more details" from the review screen going back
     * through AI again). Idempotent per (row, submission_token).
     *
     * @param  array<string, mixed>  $data
     */
    public function intakeRetryClassification(Customer $customer, CustomerRequest $request, array $data, ?UploadedFile $image, string $submissionToken): CustomerRequest
    {
        if ((int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $this->customerContactAbuseService->assertCanCreate($customer);

        $additional = isset($data['additional_details']) ? (string) $data['additional_details'] : null;
        if (is_string($additional) && trim($additional) !== '') {
            $layerOne = $this->contactInformationScanner->scanText($additional);
            if ($layerOne->detected) {
                $this->customerContactAbuseService->blockAndSuspend($customer, $layerOne->types);
            }
        }

        // See the identical comment in intakeClassify() — dispatch must
        // happen only after the per-customer lock is released.
        $dispatch = null;

        $updated = $this->duplicateDetectionService->runSerialized($customer, function () use ($customer, $request, $additional, $image, $submissionToken, &$dispatch) {
            $request->refresh();

            $accepted = $this->idempotency->find($customer, IntakeAction::Retry, $submissionToken);
            if ($accepted !== null) {
                if ((int) $accepted->customer_request_id !== (int) $request->id) {
                    throw ValidationException::withMessages([
                        'request_text' => 'This request can no longer be classified.',
                    ]);
                }

                return $request; // idempotent resend of the exact same retry click
            }

            if ($request->submission_token === $submissionToken) {
                $this->idempotency->remember($customer, $request, IntakeAction::Retry, $submissionToken);

                return $request;
            }

            // ai_stage === null is a legacy row (classified synchronously
            // before this pipeline existed) — resumable exactly like
            // before as long as it's still open (PendingClassification).
            // It simply graduates into the async pipeline from here on.
            $legacyOpen = $request->ai_stage === null && $request->status === RequestStatus::PendingClassification;
            if (! $legacyOpen && ($request->ai_stage === null || ! $request->ai_stage->isTerminalClassification())) {
                throw ValidationException::withMessages([
                    'request_text' => 'This request can no longer be classified.',
                ]);
            }

            $this->customerRequestLimitService->assertNoClassificationInFlight($customer, exceptRequestId: $request->id);

            $jobToken = $this->stageService->newToken();
            $result = $this->customerRequestService->rearmForClassification($request, $additional, $image, $submissionToken, $jobToken);

            $dispatch = ['id' => $result->id, 'token' => $jobToken];

            return $result;
        });

        if ($dispatch !== null) {
            ClassifyCustomerRequestJob::dispatch($dispatch['id'], $dispatch['token'])->afterCommit();
        }

        return $updated;
    }

    /**
     * Confirm a suggested category (either an explicit RequestClassification
     * — the "confirm" route — or the row's own latest classification — the
     * "finalizeWithCategory" resume-review route). Both funnel here.
     * Idempotent per (row, submission_token): does NOT touch quota/credit —
     * that only ever happens inside FinalizeCustomerRequestJob.
     */
    public function intakeConfirm(Customer $customer, CustomerRequest $request, RequestClassification $classification, string $categoryPublicId, string $submissionToken): CustomerRequest
    {
        if ((int) $request->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ((int) $classification->customer_request_id !== (int) $request->id) {
            abort(404);
        }

        $accepted = $this->idempotency->find($customer, IntakeAction::Confirm, $submissionToken);
        if ($accepted !== null) {
            if ((int) $accepted->customer_request_id !== (int) $request->id) {
                throw ValidationException::withMessages([
                    'category_id' => 'This request can no longer be classified.',
                ]);
            }

            return $request->fresh(['image']);
        }

        $outcome = $this->stageService->guardedTransition(
            $request->id,
            acceptedStages: null,
            expectedToken: null,
            mutate: function (CustomerRequest $locked) use ($customer, $classification, $categoryPublicId, $submissionToken) {
                $alreadyAccepted = $this->idempotency->find($customer, IntakeAction::Confirm, $submissionToken);
                if ($alreadyAccepted !== null && (int) $alreadyAccepted->customer_request_id === (int) $locked->id) {
                    return ['noop' => true, 'token' => null];
                }

                if ($locked->submission_token === $submissionToken
                    && in_array($locked->ai_stage, [
                        AiStage::QueuedFinalDuplicateCheck,
                        AiStage::CheckingFinalDuplicate,
                        AiStage::Finalizing,
                        AiStage::Ready,
                        AiStage::DuplicateBlocked,
                    ], true)
                ) {
                    $this->idempotency->remember($customer, $locked, IntakeAction::Confirm, $submissionToken);

                    return ['noop' => true, 'token' => null];
                }

                $legacyOpen = $locked->ai_stage === null && $locked->status === RequestStatus::PendingClassification;
                if (! $legacyOpen && $locked->ai_stage !== AiStage::ReadyForReview) {
                    throw ValidationException::withMessages([
                        'category_id' => 'This request can no longer be classified.',
                    ]);
                }

                $this->customerRequestLimitService->assertNoFinalizationInFlight($customer, exceptRequestId: $locked->id);

                $category = $this->assertConfirmableCategory($classification, $categoryPublicId);
                $jobToken = $this->stageService->newToken();

                $locked->submission_token = $submissionToken;
                $locked->confirmed_category_id = $category->id;
                $locked->confirmed_classification_id = $classification->id;
                $locked->ai_stage_reason = null;
                $this->stageService->advance($locked, AiStage::QueuedFinalDuplicateCheck, $jobToken);
                $this->idempotency->remember($customer, $locked, IntakeAction::Confirm, $submissionToken);

                return ['noop' => false, 'token' => $jobToken];
            },
        );

        if ($outcome === null) {
            abort(404);
        }

        if ($outcome['noop'] === false) {
            FinalizeCustomerRequestJob::dispatch($request->id, $outcome['token'])->afterCommit();
        }

        return $request->fresh(['image']);
    }

    private function findBySubmissionToken(Customer $customer, string $submissionToken): ?CustomerRequest
    {
        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->where('submission_token', $submissionToken)
            ->first();
    }

    // =====================================================================
    // AI-calling body — invoked only from ClassifyCustomerRequestJob
    // =====================================================================

    /**
     * @return array{classification: RequestClassification, failed: bool, comparable: bool}
     */
    public function runClassificationAttempt(CustomerRequest $request): array
    {
        $input = $this->buildInput($request);

        try {
            $raw = $this->provider->classify($input);
            $sanitized = $this->sanitizeResult($raw);

            if ($this->contactInformationScanner->confirmedFromAi($sanitized)) {
                $request->loadMissing('customer');
                if ($request->customer !== null) {
                    $this->customerContactAbuseService->suspendForContact(
                        $request->customer,
                        $sanitized->contactInformationTypes !== [] ? $sanitized->contactInformationTypes : ['phone'],
                    );
                }

                $classification = $this->storeAttempt($request, $sanitized, ClassificationStatus::Failed, $input->hasImage);

                return ['classification' => $classification, 'failed' => true, 'comparable' => false];
            }

            $status = $this->statusFor($sanitized);
            $classification = $this->storeAttempt($request, $sanitized, $status, $input->hasImage);

            $snapshot = $this->normalizedRequestSnapshotService->fromClassificationResult(
                $sanitized,
                $this->categoryFromPublicId($sanitized->primaryCategoryPublicId),
            );
            $comparable = NormalizedRequestSnapshot::isComparable($snapshot);
            if ($comparable) {
                $this->normalizedRequestSnapshotService->store($request, $snapshot);
            }

            return ['classification' => $classification, 'failed' => false, 'comparable' => $comparable];
        } catch (Throwable $exception) {
            report($exception);

            $classification = $this->storeAttempt(
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

            return ['classification' => $classification, 'failed' => true, 'comparable' => false];
        }
    }

    // =====================================================================
    // Customer-facing presentation
    // =====================================================================

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
     * Polling / Inertia-prop payload describing where a pending row is in
     * the async pipeline. Safe to call for legacy rows (ai_stage null).
     *
     * @return array<string, mixed>
     */
    public function statusPayload(CustomerRequest $request): array
    {
        $request->loadMissing(['duplicateOf:id,public_id', 'customer']);
        $stage = $request->ai_stage;
        $suspended = (bool) ($request->customer?->isSuspended());

        $payload = [
            'request_public_id' => $request->public_id,
            'status' => $request->status->name,
            'ai_stage' => $stage?->value,
            'poll' => $stage !== null && ($stage->isClassificationInFlight() || $stage->isFinalizationInFlight()),
            'poll_interval_ms' => CustomerRequestPipelineConfig::statusPollIntervalMs(),
            'poll_timeout_ms' => CustomerRequestPipelineConfig::statusPollTimeoutMs(),
            'message' => null,
            'classification' => null,
            'duplicate_of_request_public_id' => null,
            'suspended' => $suspended,
            'quota_exhausted' => false,
        ];

        if ($stage === null) {
            // Legacy row, created before this pipeline existed.
            if ($request->status === RequestStatus::PendingClassification) {
                $latest = $request->latestClassification()->first();
                $payload['classification'] = $latest ? $this->presentForCustomer($latest) : null;
            }

            return $payload;
        }

        if ($stage->isClassificationInFlight() || $stage->isFinalizationInFlight()) {
            $payload['message'] = $stage->isFinalizationInFlight()
                ? CustomerRequestMessages::finalizing()
                : CustomerRequestMessages::processing();

            return $payload;
        }

        if ($stage === AiStage::ReadyForReview) {
            $latest = $request->latestClassification()->first();
            $payload['classification'] = $latest ? $this->presentForCustomer($latest) : null;
            if ($request->ai_stage_reason === 'quota_exhausted_at_finalization') {
                $payload['message'] = CustomerRequestMessages::quotaExhaustedAtFinalization();
                $payload['quota_exhausted'] = true;
            }

            return $payload;
        }

        if ($stage === AiStage::Failed) {
            $latest = $request->latestClassification()->first();
            $payload['classification'] = $latest ? $this->presentForCustomer($latest) : null;
            $payload['message'] = $suspended
                ? CustomerRequestMessages::suspended()
                : CustomerRequestMessages::classificationFailed();

            return $payload;
        }

        if ($stage === AiStage::DuplicateBlocked) {
            $payload['message'] = CustomerRequestMessages::duplicateRequest();
            $payload['duplicate_of_request_public_id'] = $request->duplicateOf?->public_id;

            return $payload;
        }

        if ($stage === AiStage::Expired) {
            $payload['message'] = CustomerRequestMessages::classificationFailed();
        }

        return $payload;
    }

    // =====================================================================
    // Shared private helpers
    // =====================================================================

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistAfterScan(
        Customer $customer,
        ?CustomerRequest $pending,
        array $data,
        ?UploadedFile $image,
        ?ClassificationResult $result
    ): CustomerRequest {
        $snapshot = $result instanceof ClassificationResult
            ? $this->normalizedRequestSnapshotService->fromClassificationResult(
                $result,
                $this->categoryFromPublicId($result->primaryCategoryPublicId),
            )
            : [];

        if ($pending instanceof CustomerRequest) {
            $request = $this->customerRequestService->appendDetailsAndMaybeReplaceImage(
                $pending,
                isset($data['additional_details']) ? (string) $data['additional_details'] : null,
                $image,
            );

            if (NormalizedRequestSnapshot::isComparable($snapshot)) {
                $this->normalizedRequestSnapshotService->store($request, $snapshot);
            }

            return $request;
        }

        if (! NormalizedRequestSnapshot::isComparable($snapshot)) {
            return $this->customerRequestService->storePendingForCustomer($customer, $data, $image);
        }

        return $this->duplicateDetectionService->persistIfNotDuplicate(
            $customer,
            $snapshot,
            fn () => $this->customerRequestService->storePendingForCustomer($customer, $data, $image),
        );
    }

    private function assertNotDuplicateOrDiscard(
        Customer $customer,
        CustomerRequest $request,
        RequestClassification $classification,
        Category $category
    ): void {
        $snapshot = $this->normalizedRequestSnapshotService->fromPersisted($request, $classification, $category);
        $snapshot['category_public_id'] = $category->public_id;
        $snapshot['category_name_en'] = $category->name_en;
        $snapshot['category_name_ar'] = $category->name_ar;

        try {
            $this->duplicateDetectionService->assertNotDuplicate($customer, $snapshot, (int) $request->id);
        } catch (DuplicateCustomerRequestException $exception) {
            $this->customerRequestService->discardPendingUnfinalized($request);

            throw $exception;
        }

        $this->normalizedRequestSnapshotService->store($request, $snapshot);
    }

    private function existingPendingRequest(Customer $customer, mixed $pendingPublicId): ?CustomerRequest
    {
        if (! is_string($pendingPublicId) || $pendingPublicId === '') {
            return null;
        }

        $existing = CustomerRequest::query()->where('public_id', $pendingPublicId)->first();

        if ($existing === null || (int) $existing->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($existing->status !== RequestStatus::PendingClassification) {
            throw ValidationException::withMessages([
                'request_text' => 'This request can no longer be classified.',
            ]);
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function classificationText(?CustomerRequest $pending, array $data): string
    {
        $text = $pending instanceof CustomerRequest
            ? (string) $pending->request_text
            : (string) ($data['request_text'] ?? '');
        $additional = trim((string) ($data['additional_details'] ?? ''));

        if ($additional !== '') {
            $text = trim($text."\n".$additional);
        }

        return $text;
    }

    private function buildInputFromParts(string $text, ?UploadedFile $image, ?CustomerRequest $existing): ClassificationInput
    {
        $contents = null;
        $mime = null;
        $size = null;
        $hasImage = false;

        if ($image instanceof UploadedFile) {
            $hasImage = true;
            $mime = $image->getMimeType();
            $size = $image->getSize() ?: null;
            $contents = file_get_contents($image->getRealPath()) ?: null;
        } elseif ($existing instanceof CustomerRequest) {
            $existing->loadMissing('image');
            $stored = $existing->image;
            if ($stored instanceof RequestImage && is_string($stored->path) && $stored->path !== '') {
                $disk = Storage::disk(RequestImage::DISK);
                if ($disk->exists($stored->path)) {
                    $hasImage = true;
                    $mime = $stored->mime_type;
                    $size = $stored->size;
                    $contents = $disk->get($stored->path);
                }
            }
        }

        return new ClassificationInput(
            requestText: $text,
            hasImage: $hasImage,
            imageMime: $mime,
            imageSize: $size,
            imageContents: $contents,
            taxonomy: $this->taxonomyForProvider(),
        );
    }

    private function assertPendingClassification(CustomerRequest $request): void
    {
        if ($request->status !== RequestStatus::PendingClassification) {
            throw ValidationException::withMessages([
                'category_id' => 'This request can no longer be classified.',
            ]);
        }
    }

    private function categoryFromPublicId(?string $publicId): ?Category
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return Category::query()->where('public_id', $publicId)->first();
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
            contactInformationDetected: $result->contactInformationDetected,
            contactInformationTypes: $this->contactInformationScanner->sanitizedTypes($result->contactInformationTypes),
            contactDetectionConfidence: $this->clampConfidence($result->contactDetectionConfidence),
            contactEvidenceSummary: $result->contactEvidenceSummary,
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

    private function assertConfirmableCategory(RequestClassification $classification, string $categoryPublicId): Category
    {
        $classification->loadMissing('suggestedCategory');
        $allowed = collect($this->presentSuggestions($classification))
            ->pluck('category_public_id')
            ->all();

        if (! in_array($categoryPublicId, $allowed, true)) {
            throw ValidationException::withMessages([
                'category_id' => CustomerRequestMessages::confirmSuggestedOnly(),
            ]);
        }

        return $this->requireActiveCategory($categoryPublicId);
    }
}
