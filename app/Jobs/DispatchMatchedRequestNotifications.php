<?php

namespace App\Jobs;

use App\Services\MatchedRequestPushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchMatchedRequestNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

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

    public function handle(MatchedRequestPushDispatcher $dispatcher): void
    {
        if ($this->customerRequestId < 1 || $this->matchIds === []) {
            return;
        }

        $dispatcher->notify($this->matchIds, $this->customerRequestId);
    }
}
