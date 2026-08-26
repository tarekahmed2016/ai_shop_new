<?php

namespace App\Services;

use App\Jobs\DispatchMatchedRequestNotifications;
use App\Models\RequestMatch;
use App\Models\User;
use App\Notifications\MatchedCustomerRequestNotification;
use Illuminate\Support\Facades\Cache;
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
    public function dispatchAfterCommit(int $customerRequestId, array $matchIds): void
    {
        $ids = array_values(array_unique(array_filter($matchIds, fn ($id) => is_int($id) && $id > 0)));

        if ($customerRequestId < 1 || $ids === []) {
            return;
        }

        DB::afterCommit(function () use ($customerRequestId, $ids): void {
            try {
                DispatchMatchedRequestNotifications::dispatch($customerRequestId, $ids);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * @param  list<int>  $matchIds
     */
    public function notify(array $matchIds, ?int $customerRequestId = null): void
    {
        $this->recipientResolver->eachMatchRecipients(
            $matchIds,
            function (RequestMatch $match, $users): void {
                foreach ($users as $user) {
                    if (! $user instanceof User) {
                        continue;
                    }

                    $this->queueRecipientNotification($match, $user);
                }
            },
            $customerRequestId,
        );
    }

    private function queueRecipientNotification(RequestMatch $match, User $user): void
    {
        $key = $this->idempotencyKey((int) $match->id, (int) $user->id);

        if (! Cache::add($key, 1, now()->addDay())) {
            return;
        }

        try {
            Notification::send($user, new MatchedCustomerRequestNotification($match));
        } catch (Throwable $exception) {
            Cache::forget($key);
            report($exception);
        }
    }

    private function idempotencyKey(int $matchId, int $userId): string
    {
        return "matched-request-notification:{$matchId}:{$userId}";
    }
}
