<?php

namespace App\Jobs;

use App\Services\MatchedRequestPushDispatcher;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one bounded chunk of matched-request notification recipients.
 *
 * This job is dispatched exclusively by DispatchMatchedRequestNotifications,
 * which splits a request's (potentially large) match set into many of these
 * jobs — each carrying at most `notifications.matched_request_chunk_size`
 * match IDs (~200) — inside a single Bus batch. Because every chunk job's
 * workload is bounded, no single job execution scales with the number of
 * merchants a request matches, however large that number grows.
 *
 * The actual recipient resolution + send/idempotency logic is unchanged:
 * it is delegated verbatim to MatchedRequestPushDispatcher::notify(), the
 * same method the previous single-job implementation called directly.
 */
class DispatchMatchedRequestNotificationChunk implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    /**
     * @param  list<int>  $matchIds  bounded to the configured chunk size
     * @param  int  $chunkIndex  1-based position of this chunk within the batch (for logging only)
     * @param  int  $chunkCount  total number of chunks in the batch (for logging only)
     */
    public function __construct(
        public int $customerRequestId,
        public array $matchIds,
        public int $chunkIndex,
        public int $chunkCount,
    ) {}

    public function handle(MatchedRequestPushDispatcher $dispatcher): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->customerRequestId < 1 || $this->matchIds === []) {
            return;
        }

        $dispatcher->notify($this->matchIds, $this->customerRequestId);
    }

    /**
     * Logged when this chunk exhausts all retries. Per-recipient WebPush
     * send failures are already caught and reported individually inside
     * MatchedRequestPushDispatcher and never reach this point — this only
     * fires for failures in the chunk's own recipient-resolution step
     * (e.g. a database error), so the remaining chunks in the batch are
     * unaffected and continue to notify their own recipients.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Matched-request notification chunk failed permanently', [
            'customer_request_id' => $this->customerRequestId,
            'chunk_index' => $this->chunkIndex,
            'chunk_count' => $this->chunkCount,
            'match_ids_count' => count($this->matchIds),
            'batch_id' => $this->batch()?->id,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
