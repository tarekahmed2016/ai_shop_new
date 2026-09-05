<?php

use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stuck-AI recovery + idle-attempt expiry. This does NOT run just because
// a queue worker is up. Production MUST have a system cron (or equivalent
// systemd timer) invoking `php artisan schedule:run` every minute:
//
//   * * * * * cd /opt/lampp/htdocs/ai_shop_new && php artisan schedule:run >> /dev/null 2>&1
//
// That cron is what makes Laravel fire `customer-requests:recover-stuck-ai`
// every `customer_requests.stuck_ai_recovery_every_minutes` minutes.
// Running the artisan command by hand is the supported one-off fallback
// when cron is down. See README "Queue & scheduler".
$recoveryEveryMinutes = CustomerRequestPipelineConfig::stuckAiRecoveryEveryMinutes();

Schedule::command('customer-requests:recover-stuck-ai')
    ->cron('*/'.$recoveryEveryMinutes.' * * * *')
    ->withoutOverlapping()
    ->onOneServer();

$matchingRecoveryEveryMinutes = CustomerRequestPipelineConfig::matchingRecoveryEveryMinutes();

Schedule::command('customer-requests:recover-pending-matching')
    ->cron('*/'.$matchingRecoveryEveryMinutes.' * * * *')
    ->withoutOverlapping()
    ->onOneServer();

// Retention. jobs rows are deleted by the worker on success; these tables
// are not. Finished batches (one per matched request), failed jobs, and
// expired database-cache keys would otherwise grow without bound.
Schedule::command('queue:prune-batches --hours=48')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('cache:prune-expired')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
