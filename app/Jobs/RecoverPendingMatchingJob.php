<?php

namespace App\Jobs;

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Models\CustomerRequest;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Re-dispatches matching for Ready rows whose matching never completed.
 *
 * Covers the crash window after the Ready transaction commits but before
 * MatchCustomerRequestJob is inserted into the queue. Invoked inline by
 * `customer-requests:recover-pending-matching` (not via the AI queue) so a
 * backed-up worker cannot delay the sweep.
 *
 * A successful sync that creates zero matches is completion, not failure.
 * Two sweeps serialize on the request row lock; a recent last-attempt
 * means a live job (or a just-claimed recovery dispatch) owns the row.
 */
class RecoverPendingMatchingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(): void
    {
        $staleBefore = now()->subMinutes(CustomerRequestPipelineConfig::matchingRecoveryStaleMinutes());

        $ids = CustomerRequest::query()
            ->where('status', RequestStatus::Ready)
            ->whereNull('matching_completed_at')
            ->where(function ($query) use ($staleBefore) {
                $query->whereNull('matching_last_attempt_at')
                    ->orWhere('matching_last_attempt_at', '<=', $staleBefore);
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $shouldDispatch = DB::transaction(function () use ($id, $staleBefore) {
                $locked = CustomerRequest::query()->whereKey($id)->lockForUpdate()->first();

                if ($locked === null) {
                    return false;
                }

                if ($locked->status !== RequestStatus::Ready) {
                    return false;
                }

                if ($locked->matching_completed_at !== null) {
                    return false;
                }

                if ($locked->matching_last_attempt_at !== null
                    && $locked->matching_last_attempt_at->gt($staleBefore)) {
                    return false;
                }

                $locked->matching_last_attempt_at = now();
                $locked->save();

                return true;
            });

            if ($shouldDispatch) {
                MatchCustomerRequestJob::dispatch((int) $id)->afterCommit();
            }
        }
    }
}
