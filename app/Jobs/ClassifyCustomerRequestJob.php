<?php

namespace App\Jobs;

use App\Enums\CustomerRequests\AiStage;
use App\Models\CustomerRequest;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Services\RequestClassificationService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * First stage of the async AI pipeline: calls the classification provider
 * and decides where the row goes next (duplicate check, straight to
 * review, or failed). This is the ONLY place the classification AI
 * provider is invoked on the async path. The legacy synchronous
 * classify() method still calls the provider inline while
 * classification.async_enabled is false.
 *
 * Every write is guarded by CustomerRequestAiStageService, which re-checks
 * the row's current ai_stage/ai_job_token under a row lock immediately
 * before writing. A stale token (superseded by a retry, a stuck-recovery
 * re-dispatch, or the row moving on for any other reason) makes both the
 * "claim" step and the "commit result" step safe no-ops.
 */
class ClassifyCustomerRequestJob implements ShouldQueue
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
        $this->timeout = (int) config('classification.timeout') + 15;
    }

    public function handle(RequestClassificationService $classificationService, CustomerRequestAiStageService $stageService): void
    {
        $claimed = $stageService->guardedTransition(
            $this->customerRequestId,
            [AiStage::QueuedClassification, AiStage::Classifying],
            $this->token,
            function (CustomerRequest $request) use ($stageService) {
                // resetAttempts: false — this is the same attempt lineage,
                // not a fresh customer action.
                $stageService->advance($request, AiStage::Classifying, $this->token, resetAttempts: false);

                return true;
            },
        );

        if ($claimed === null) {
            return; // stale token, or the row already moved on
        }

        $request = CustomerRequest::query()->find($this->customerRequestId);
        if ($request === null) {
            return;
        }

        $outcome = $classificationService->runClassificationAttempt($request);

        $nextToken = $stageService->guardedTransition(
            $this->customerRequestId,
            AiStage::Classifying,
            $this->token,
            function (CustomerRequest $locked) use ($stageService, $outcome) {
                if ($outcome['failed']) {
                    $stageService->advance($locked, AiStage::Failed, null);

                    return null;
                }

                if ($outcome['comparable']) {
                    $newToken = $stageService->newToken();
                    $stageService->advance($locked, AiStage::QueuedDuplicateCheck, $newToken);

                    return $newToken;
                }

                $stageService->advance($locked, AiStage::ReadyForReview, null);

                return null;
            },
        );

        if ($nextToken !== null) {
            DetectDuplicateCustomerRequestJob::dispatch($this->customerRequestId, $nextToken)->afterCommit();
        }
    }

    /**
     * Only reached after every retry is exhausted (e.g. a real infra
     * failure, not a provider error — those are already caught and turned
     * into a stored Failed classification inside runClassificationAttempt).
     * Guarded exactly like every other write: a token that already moved
     * on (a newer retry/stuck-recovery dispatch already succeeded) makes
     * this a safe no-op, so an old failed() callback can never clobber a
     * newer attempt's result.
     */
    public function failed(Throwable $exception): void
    {
        app(CustomerRequestAiStageService::class)->guardedTransition(
            $this->customerRequestId,
            [AiStage::QueuedClassification, AiStage::Classifying],
            $this->token,
            function (CustomerRequest $request) {
                app(CustomerRequestAiStageService::class)->advance($request, AiStage::Failed, null);
            },
        );

        Log::error('customer_request.classify_job_failed', [
            'customer_request_id' => $this->customerRequestId,
            'token' => $this->token,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
