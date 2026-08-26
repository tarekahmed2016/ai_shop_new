<?php

namespace Tests\Benchmark;

use Generator;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;

/**
 * Production WebPush stack except flush(), which would POST to push services.
 */
final class SilentFlushWebPush extends WebPush
{
    public int $queuedNotifications = 0;

    public int $flushCalls = 0;

    public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
    {
        $this->queuedNotifications++;

        parent::queueNotification($subscription, $payload, $options, $auth);
    }

    public function flush(?int $batchSize = null): Generator
    {
        $this->flushCalls++;
        $this->notifications = [];

        yield from [];
    }
}
