<?php

namespace App\Jobs;

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Models\CustomerRequest;
use App\Services\RequestMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs merchant matching after a customer request is durably Ready.
 *
 * Matching is deliberately NOT part of FinalizeCustomerRequestJob: that
 * worker already spent its timeout budget on the final duplicate check,
 * and a matching failure must never be able to look like an AI
 * finalization failure. Re-running this job is idempotent — live matches,
 * history rows, and notifications are created only for newly eligible
 * merchants (see RequestMatchingService::sync()).
 *
 * Queue: the connection default (`default`). Matching is a bounded DB
 * write, same family as notification fan-out; it must not occupy the AI
 * processing queue. A dedicated matching queue is not required.
 */
class MatchCustomerRequestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Stay under the database connection `retry_after` (150s). Matching
     * is chunked inserts, not an AI call; 120s matches the notification
     * chunk jobs that this work may enqueue after commit.
     */
    public int $timeout = 120;

    public function __construct(
        public int $customerRequestId,
    ) {}

    public function handle(RequestMatchingService $requestMatchingService): void
    {
        $request = $this->claim();
        if ($request === null) {
            return;
        }

        $request->loadMissing('category');
        $requestMatchingService->sync($request);
    }

    /**
     * Record an attempt under the row lock. Deleted and non-Ready rows
     * (cancelled/closed) are a no-op. Already-completed rows still sync:
     * a direct retry is harmless and idempotent; recovery will not
     * requeue those rows.
     */
    private function claim(): ?CustomerRequest
    {
        return DB::transaction(function () {
            $locked = CustomerRequest::query()
                ->whereKey($this->customerRequestId)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== RequestStatus::Ready) {
                return null;
            }

            $locked->matching_last_attempt_at = now();
            $locked->save();

            return $locked;
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('customer_request.matching_job_failed', [
            'customer_request_id' => $this->customerRequestId,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
