<?php

namespace App\Jobs;

use App\Services\MatchedRequestRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates matched-request notification fan-out for one customer
 * request.
 *
 * This job never resolves recipients and never sends notifications
 * itself. It only splits the (potentially large) set of match IDs into
 * bounded chunks (~200 each, see notifications.matched_request_chunk_size)
 * and dispatches one DispatchMatchedRequestNotificationChunk job per
 * chunk inside a single Bus batch. This keeps every individual job's
 * work bounded regardless of how many merchants a request matches, so a
 * request with 10,000 matched merchants produces ~50 small, independent,
 * retryable jobs instead of one job that must process all 10,000 within
 * a single timeout window.
 */
class DispatchMatchedRequestNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Splitting match IDs and dispatching a batch is cheap, in-memory work
     * (no recipient queries happen here), so this job needs a much smaller
     * timeout than the per-chunk jobs it creates.
     */
    public int $timeout = 30;

    /**
     * @param  list<int>  $matchIds
     */
    public function __construct(
        public int $customerRequestId,
        public array $matchIds,
    ) {
        $this->matchIds = array_values(array_unique(array_filter(
            $matchIds,
            fn ($id) => is_int($id) && $id > 0
        )));
    }

    public function handle(MatchedRequestRecipientResolver $recipientResolver): void
    {
        if ($this->customerRequestId < 1 || $this->matchIds === []) {
            return;
        }

        $chunks = array_chunk($this->matchIds, $recipientResolver->chunkSize());
        $chunkCount = count($chunks);

        $jobs = [];
        foreach ($chunks as $index => $chunkIds) {
            $jobs[] = new DispatchMatchedRequestNotificationChunk(
                $this->customerRequestId,
                array_values($chunkIds),
                $index + 1,
                $chunkCount,
            );
        }

        Bus::batch($jobs)
            ->name("matched-request-notifications:{$this->customerRequestId}")
            // A failure resolving/sending one chunk must never block the
            // other chunks' merchants from being notified.
            ->allowFailures()
            ->dispatch();
    }

    /**
     * Logged only if the batch itself could never be created/dispatched
     * (e.g. a database error writing to job_batches) after exhausting
     * retries. Failures inside an already-dispatched chunk are logged by
     * DispatchMatchedRequestNotificationChunk::failed() instead.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Matched-request notification dispatch failed permanently', [
            'customer_request_id' => $this->customerRequestId,
            'match_ids_count' => count($this->matchIds),
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
