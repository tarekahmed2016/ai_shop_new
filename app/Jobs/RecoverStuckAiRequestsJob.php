<?php

namespace App\Jobs;

use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Models\CustomerRequest;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Recovery/expiry handler invoked by `customer-requests:recover-stuck-ai`
 * (see routes/console.php). It is intentionally NOT dispatched onto the
 * AI processing queue — a backed-up AI worker must not delay the sweep
 * that exists to recover those workers. Two responsibilities:
 *
 *  1. Recover rows stuck in an in-flight ai_stage — a worker crashed,
 *     was killed mid-job, or a job payload was lost — by minting a fresh
 *     ai_job_token and re-dispatching the appropriate job for that stage
 *     group. Bounded by `stuck_ai_max_recovery_attempts`; beyond that the
 *     row is marked Failed rather than retried forever.
 *
 *  2. Expire abandoned rows sitting idle in ReadyForReview/Failed past
 *     `open_attempt_ttl_hours`, freeing the customer's open-attempt
 *     ceiling.
 *
 * Every write goes through the same guarded-transition primitive as the
 * pipeline jobs, so this sweep can never clobber a row a live worker just
 * advanced (the staleness condition is re-checked under the row lock).
 */
class RecoverStuckAiRequestsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(CustomerRequestAiStageService $stageService): void
    {
        $this->recoverStuck($stageService);
        $this->expireAbandoned($stageService);
    }

    private function recoverStuck(CustomerRequestAiStageService $stageService): void
    {
        $thresholdMinutes = CustomerRequestPipelineConfig::stuckAiThresholdMinutes();
        $maxAttempts = CustomerRequestPipelineConfig::stuckAiMaxRecoveryAttempts();
        $cutoff = now()->subMinutes($thresholdMinutes);

        $stageValues = array_map(fn (AiStage $s) => $s->value, AiStage::anyInFlight());

        $ids = CustomerRequest::query()
            ->whereIn('ai_stage', $stageValues)
            ->where('ai_stage_updated_at', '<', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            $outcome = $stageService->guardedTransition(
                (int) $id,
                AiStage::anyInFlight(),
                null,
                function (CustomerRequest $request) use ($stageService, $maxAttempts, $cutoff) {
                    // Re-check staleness under the lock: a live worker may
                    // have progressed this row between the pluck() above
                    // and acquiring the lock here.
                    if ($request->ai_stage_updated_at === null || $request->ai_stage_updated_at->gte($cutoff)) {
                        return null;
                    }

                    if ($request->ai_attempts >= $maxAttempts) {
                        Log::warning('customer_request.ai_stage_recovery_exhausted', [
                            'customer_request_id' => $request->id,
                            'ai_stage' => $request->ai_stage?->value,
                            'ai_attempts' => $request->ai_attempts,
                        ]);

                        if ($request->ai_stage?->isFinalizationInFlight()) {
                            $stageService->advance($request, AiStage::ReadyForReview, null);
                        } else {
                            $stageService->advance($request, AiStage::Failed, null);
                        }

                        return null;
                    }

                    $stage = $request->ai_stage;
                    $recoveryStage = match ($stage) {
                        AiStage::QueuedClassification, AiStage::Classifying => AiStage::QueuedClassification,
                        AiStage::QueuedDuplicateCheck, AiStage::CheckingDuplicate => AiStage::QueuedDuplicateCheck,
                        AiStage::QueuedFinalDuplicateCheck, AiStage::CheckingFinalDuplicate, AiStage::Finalizing => AiStage::QueuedFinalDuplicateCheck,
                        default => $stage,
                    };
                    $newToken = $stageService->newToken();
                    $request->ai_attempts += 1;
                    $stageService->advance($request, $recoveryStage, $newToken, resetAttempts: false);

                    Log::warning('customer_request.ai_stage_recovery_redispatched', [
                        'customer_request_id' => $request->id,
                        'from_ai_stage' => $stage?->value,
                        'to_ai_stage' => $recoveryStage?->value,
                        'attempt' => $request->ai_attempts,
                    ]);

                    return ['stage' => $recoveryStage, 'token' => $newToken, 'id' => $request->id];
                },
            );

            if ($outcome === null) {
                continue;
            }

            match ($outcome['stage']) {
                AiStage::QueuedClassification => ClassifyCustomerRequestJob::dispatch($outcome['id'], $outcome['token'])->afterCommit(),
                AiStage::QueuedDuplicateCheck => DetectDuplicateCustomerRequestJob::dispatch($outcome['id'], $outcome['token'])->afterCommit(),
                AiStage::QueuedFinalDuplicateCheck => FinalizeCustomerRequestJob::dispatch($outcome['id'], $outcome['token'])->afterCommit(),
                default => null,
            };
        }
    }

    private function expireAbandoned(CustomerRequestAiStageService $stageService): void
    {
        $ttlHours = CustomerRequestPipelineConfig::openAttemptTtlHours();
        $cutoff = now()->subHours($ttlHours);

        $ids = CustomerRequest::query()
            ->whereIn('ai_stage', [AiStage::ReadyForReview->value, AiStage::Failed->value])
            ->where('ai_stage_updated_at', '<', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            $stageService->guardedTransition(
                (int) $id,
                [AiStage::ReadyForReview, AiStage::Failed],
                null,
                function (CustomerRequest $request) use ($stageService, $cutoff) {
                    if ($request->ai_stage_updated_at === null || $request->ai_stage_updated_at->gte($cutoff)) {
                        return;
                    }

                    $request->status = RequestStatus::Cancelled;
                    $stageService->advance($request, AiStage::Expired, null);
                },
            );
        }
    }
}
