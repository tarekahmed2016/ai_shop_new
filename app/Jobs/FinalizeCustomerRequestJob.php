<?php

namespace App\Jobs;

use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Exceptions\DuplicateCustomerRequestException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Services\CustomerExtraRequestService;
use App\Services\CustomerRequestDuplicateDetectionService;
use App\Services\CustomerRequestLimitService;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Services\CustomerRequestService;
use App\Services\NormalizedRequestSnapshotService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Final stage of the async pipeline, dispatched only from
 * RequestClassificationService::intakeConfirm(). Responsible for:
 *
 *  1. Re-running the duplicate check against the category the customer
 *     actually confirmed (which may differ from the primary suggestion
 *     already checked by DetectDuplicateCustomerRequestJob).
 *  2. The single authoritative quota/extra-credit consumption point for
 *     the entire pipeline — performed exactly once, atomically, in the
 *     same transaction that flips status to Ready.
 *
 * On the async path this is the only writer of quota_consumed_at and
 * Status::Ready. The legacy synchronous confirm()/finalizeReady() path
 * (classification.async_enabled=false) still consumes quota at pending
 * persist time and flips Ready inline.
 */
class FinalizeCustomerRequestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @var list<int>
     */
    public array $backoff = [5];

    public int $timeout;

    public function __construct(
        public int $customerRequestId,
        public string $token,
    ) {
        $this->onQueue(CustomerRequestPipelineConfig::aiQueue());
        $this->timeout = (int) config('duplicate_detection.timeout') + 25;
    }

    public function handle(
        CustomerRequestDuplicateDetectionService $duplicateDetectionService,
        NormalizedRequestSnapshotService $snapshotService,
        CustomerRequestService $customerRequestService,
        CustomerRequestLimitService $limitService,
        CustomerExtraRequestService $extraRequestService,
        CustomerRequestAiStageService $stageService,
    ): void {
        $claimed = $stageService->guardedTransition(
            $this->customerRequestId,
            [AiStage::QueuedFinalDuplicateCheck, AiStage::CheckingFinalDuplicate],
            $this->token,
            function (CustomerRequest $request) use ($stageService) {
                $stageService->advance($request, AiStage::CheckingFinalDuplicate, $this->token, resetAttempts: false);

                return true;
            },
        );

        if ($claimed === null) {
            return;
        }

        $request = CustomerRequest::query()->with('customer')->find($this->customerRequestId);
        if ($request === null || $request->customer === null) {
            return;
        }

        $classification = RequestClassification::query()->find($request->confirmed_classification_id);
        $category = Category::query()->find($request->confirmed_category_id);

        if ($classification === null || $category === null) {
            $stageService->guardedTransition(
                $this->customerRequestId,
                AiStage::CheckingFinalDuplicate,
                $this->token,
                function (CustomerRequest $locked) use ($stageService) {
                    $stageService->advance($locked, AiStage::Failed, null);
                },
            );

            return;
        }

        $snapshot = $snapshotService->fromPersisted($request, $classification, $category);
        $snapshot['category_public_id'] = $category->public_id;
        $snapshot['category_name_en'] = $category->name_en;
        $snapshot['category_name_ar'] = $category->name_ar;

        $matchedRequestId = null;

        try {
            $duplicateDetectionService->runSerialized($request->customer, function () use ($duplicateDetectionService, $request, $snapshot) {
                $duplicateDetectionService->assertNotDuplicate($request->customer, $snapshot, (int) $request->id);
            });
        } catch (DuplicateCustomerRequestException $exception) {
            $matchedRequestId = (int) $exception->matchedRequest->id;
        }

        if ($matchedRequestId !== null) {
            $stageService->guardedTransition(
                $this->customerRequestId,
                AiStage::CheckingFinalDuplicate,
                $this->token,
                function (CustomerRequest $locked) use ($customerRequestService, $matchedRequestId) {
                    $customerRequestService->markDuplicateBlocked($locked, $matchedRequestId);
                },
            );

            return;
        }

        $movedToFinalizing = $stageService->guardedTransition(
            $this->customerRequestId,
            AiStage::CheckingFinalDuplicate,
            $this->token,
            function (CustomerRequest $locked) use ($stageService, $snapshotService, $snapshot) {
                // Overwrite the stored snapshot with the *confirmed*
                // category (it previously reflected only the primary
                // suggestion), exactly matching legacy pre-async behavior.
                $snapshotService->store($locked, $snapshot);
                $stageService->advance($locked, AiStage::Finalizing, $this->token, resetAttempts: false);

                return true;
            },
        );

        if ($movedToFinalizing === null) {
            return;
        }

        // The one and only place in the whole pipeline that consumes
        // quota/credit and flips status to Ready — atomically together.
        $finalized = $stageService->guardedTransition(
            $this->customerRequestId,
            AiStage::Finalizing,
            $this->token,
            function (CustomerRequest $locked) use ($stageService, $limitService, $extraRequestService, $classification, $category) {
                $customer = Customer::query()->whereKey($locked->customer_id)->lockForUpdate()->first();
                if ($customer === null) {
                    $locked->ai_stage_reason = null;
                    $stageService->advance($locked, AiStage::Failed, null);

                    return false;
                }

                if ($locked->quota_consumed_at === null) {
                    $dailyQuotaExhausted = $limitService->dailyQuotaExhausted($customer);

                    if ($dailyQuotaExhausted) {
                        try {
                            $extraRequestService->consumeForNewRequest($customer, $locked);
                        } catch (ValidationException) {
                            $locked->ai_stage_reason = 'quota_exhausted_at_finalization';
                            $stageService->advance($locked, AiStage::ReadyForReview, null);

                            return false;
                        }
                    }

                    $locked->quota_consumed_at = now();
                }

                $classification->status = ClassificationStatus::Confirmed;
                $classification->customer_confirmed_category_id = $category->id;
                $classification->confirmed_at = now();
                $classification->save();

                $locked->category_id = $category->id;
                $locked->status = RequestStatus::Ready;
                $locked->ai_stage_reason = null;
                $locked->matching_completed_at = null;
                $locked->matching_last_attempt_at = null;
                $stageService->advance($locked, AiStage::Ready, null);

                return true;
            },
        );

        // Matching/notification fan-out must only ever see a row that is
        // durably Ready. Dispatch after this method's own transaction has
        // committed; do not run matching inline — a large merchant set
        // must not consume this job's remaining timeout, and a matching
        // failure must not be attributed to AI finalization.
        if ($finalized === true) {
            MatchCustomerRequestJob::dispatch((int) $this->customerRequestId)->afterCommit();
        }
    }

    public function failed(Throwable $exception): void
    {
        app(CustomerRequestAiStageService::class)->guardedTransition(
            $this->customerRequestId,
            [AiStage::QueuedFinalDuplicateCheck, AiStage::CheckingFinalDuplicate, AiStage::Finalizing],
            $this->token,
            function (CustomerRequest $request) {
                // Classification itself is still valid; only finalization
                // failed. Return the customer to review so they can confirm
                // again rather than treating the whole request as failed.
                app(CustomerRequestAiStageService::class)->advance($request, AiStage::ReadyForReview, null);
            },
        );

        Log::error('customer_request.finalize_job_failed', [
            'customer_request_id' => $this->customerRequestId,
            'token' => $this->token,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
