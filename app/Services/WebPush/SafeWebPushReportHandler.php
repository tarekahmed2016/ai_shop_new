<?php

namespace App\Services\WebPush;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\ReportHandlerInterface;
use NotificationChannels\WebPush\WebPushMessageInterface;

class SafeWebPushReportHandler implements ReportHandlerInterface
{
    public function handleReport(MessageSentReport $report, PushSubscription $subscription, WebPushMessageInterface $message): void
    {
        if ($report->isSuccess()) {
            return;
        }

        if ($report->isSubscriptionExpired()) {
            $subscriptionId = $subscription->id;
            $subscription->delete();
            Log::info('webpush.subscription_expired', [
                'push_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        Log::warning('webpush.delivery_failed', [
            'push_subscription_id' => $subscription->id,
            'success' => false,
        ]);
    }
}
