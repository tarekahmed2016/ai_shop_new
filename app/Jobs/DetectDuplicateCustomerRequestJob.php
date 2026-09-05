<?php

namespace App\Jobs;

use App\Enums\CustomerRequests\AiStage;
use App\Exceptions\DuplicateCustomerRequestException;
use App\Models\CustomerRequest;
use App\Services\CustomerRequestDuplicateDetectionService;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Services\CustomerRequestService;
use App\Services\NormalizedRequestSnapshotService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Early duplicate check, run against the primary-suggested category right
 * after classification — before the customer even sees the review screen.
 * A confirmed final check runs again at confirm time
 * (FinalizeCustomerRequestJob) because the customer may confirm a
 * different suggested category than the primary one checked here.
 *
 * The per-customer Cache::lock inside
 * CustomerRequestDuplicateDetectionService::runSerialized() is kept even
 * though the "at most one classification-in-flight row per customer" rule
 * already prevents two *early* checks from racing each other — it also
 * protects against a race with the customer's separate finalize pipeline
 * (a different row can be finalizing at the same moment).
 */
class DetectDuplicateCustomerRequestJob implements ShouldQueue
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
        $this->timeout = (int) config('duplicate_detection.timeout') + 15;
    }

    public function handle(
        CustomerRequestDuplicateDetectionService $duplicateDetectionService,
        NormalizedRequestSnapshotService $snapshotService,
        CustomerRequestService $customerRequestService,
        CustomerRequestAiStageService $stageService,
    ): void {
        $claimed = $stageService->guardedTransition(
            $this->customerRequestId,
            [AiStage::QueuedDuplicateCheck, AiStage::CheckingDuplicate],
            $this->token,
            function (CustomerRequest $request) use ($stageService) {
                $stageService->advance($request, AiStage::CheckingDuplicate, $this->token, resetAttempts: false);

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

        $snapshot = $snapshotService->fromPersisted($request);
        $matchedRequestId = null;

        try {
            $duplicateDetectionService->runSerialized($request->customer, function () use ($duplicateDetectionService, $request, $snapshot) {
                $duplicateDetectionService->assertNotDuplicate($request->customer, $snapshot, (int) $request->id);
            });
        } catch (DuplicateCustomerRequestException $exception) {
            $matchedRequestId = (int) $exception->matchedRequest->id;
        }

        $stageService->guardedTransition(
            $this->customerRequestId,
            AiStage::CheckingDuplicate,
            $this->token,
            function (CustomerRequest $locked) use ($stageService, $customerRequestService, $matchedRequestId) {
                if ($matchedRequestId !== null) {
                    $customerRequestService->markDuplicateBlocked($locked, $matchedRequestId);

                    return;
                }

                $stageService->advance($locked, AiStage::ReadyForReview, null);
            },
        );
    }

    public function failed(Throwable $exception): void
    {
        app(CustomerRequestAiStageService::class)->guardedTransition(
            $this->customerRequestId,
            [AiStage::QueuedDuplicateCheck, AiStage::CheckingDuplicate],
            $this->token,
            function (CustomerRequest $request) {
                // Fail open: an infra failure here must not permanently
                // strand the row — send it to review rather than Failed,
                // matching the AI-unavailable fail-open behavior of
                // assertNotDuplicate() itself.
                app(CustomerRequestAiStageService::class)->advance($request, AiStage::ReadyForReview, null);
            },
        );

        Log::error('customer_request.duplicate_check_job_failed', [
            'customer_request_id' => $this->customerRequestId,
            'token' => $this->token,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
