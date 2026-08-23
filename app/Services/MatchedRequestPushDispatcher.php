<?php

namespace App\Services;

use App\Models\RequestMatch;
use App\Notifications\MatchedCustomerRequestNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MatchedRequestPushDispatcher
{
    public function __construct(
        public MatchedRequestRecipientResolver $recipientResolver,
    ) {}

    /**
     * @param  list<int>  $matchIds
     */
    public function dispatchAfterCommit(array $matchIds): void
    {
        $ids = array_values(array_unique(array_filter($matchIds, fn ($id) => is_int($id) && $id > 0)));

        if ($ids === []) {
            return;
        }

        DB::afterCommit(function () use ($ids): void {
            try {
                $this->notify($ids);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * @param  list<int>  $matchIds
     */
    public function notify(array $matchIds): void
    {
        $matches = RequestMatch::query()
            ->with(['customerRequest:id,public_id,status', 'merchant:id,public_id,status'])
            ->whereIn('id', $matchIds)
            ->get();

        foreach ($matches as $match) {
            $recipients = $this->recipientResolver->usersFor($match);

            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::send($recipients, new MatchedCustomerRequestNotification($match));
        }
    }
}
