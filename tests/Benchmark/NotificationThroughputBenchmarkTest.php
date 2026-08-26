<?php

use App\Enums\Users\Status as UserStatus;
use App\Models\RequestMatch;
use App\Models\User;
use App\Notifications\MatchedCustomerRequestNotification;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Benchmark\SilentFlushWebPush;

function bindSilentWebPush(): SilentFlushWebPush
{
    $silent = new SilentFlushWebPush([], [], 30, []);
    $silent->setReuseVAPIDHeaders(true);
    $silent->setAutomaticPadding(config('webpush.automatic_padding'));

    app()->forgetInstance(WebPushChannel::class);
    app(ChannelManager::class)->forgetDrivers();

    app()->when(WebPushChannel::class)
        ->needs(WebPush::class)
        ->give(fn () => $silent);

    return $silent;
}

function seedThroughputRecipients(int $count): array
{
    $match = RequestMatch::factory()->create();
    $match->load(['customerRequest:id,public_id', 'merchant:id,public_id']);

    $now = now();
    $password = Hash::make('password');
    $userRows = [];
    $subscriptionRows = [];

    for ($i = 1; $i <= $count; $i++) {
        $userRows[] = [
            'name' => "Throughput Bench {$i}",
            'email' => "throughput-bench-{$i}@invalid.example",
            'phone' => sprintf('019%08d', $i),
            'password' => $password,
            'status' => UserStatus::Active->value,
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($userRows, 200) as $chunk) {
        User::query()->insert($chunk);
    }

    $users = User::query()
        ->where('email', 'like', 'throughput-bench-%@invalid.example')
        ->orderBy('id')
        ->get();

    foreach ($users as $user) {
        $subscriptionRows[] = [
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'endpoint' => 'https://invalid.example/webpush/bench/'.$user->id,
            'public_key' => 'BNcRdreALUnT5MkTqZ1BxRSn',
            'auth_token' => 'ttduAcStRzS9qdS7jwUzA',
            'content_encoding' => 'aes128gcm',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($subscriptionRows, 200) as $chunk) {
        PushSubscription::query()->insert($chunk);
    }

    return [$users->values(), $match];
}

function measureNotificationThroughput(int $count): array
{
    config(['queue.default' => 'database']);
    app('queue')->setDefaultDriver('database');

    $silent = bindSilentWebPush();
    [$users, $match] = seedThroughputRecipients($count);

    expect(DB::table('jobs')->count())->toBe(0);

    $enqueueStarted = hrtime(true);
    Notification::send($users, new MatchedCustomerRequestNotification($match));
    $enqueueNs = hrtime(true) - $enqueueStarted;

    expect(DB::table('jobs')->count())->toBe($count);

    $processStarted = hrtime(true);

    $exitCode = Artisan::call('queue:work', [
        'connection' => 'database',
        '--stop-when-empty' => true,
        '--sleep' => 0,
        '--rest' => 0,
        '--tries' => 1,
        '--backoff' => 0,
        '--max-time' => 120,
    ]);

    $processNs = hrtime(true) - $processStarted;
    $processed = $count - (int) DB::table('jobs')->count();
    $failed = (int) DB::table('failed_jobs')->count();

    $processSeconds = $processNs / 1e9;
    $enqueueSeconds = $enqueueNs / 1e9;
    $totalSeconds = ($enqueueNs + $processNs) / 1e9;

    return [
        'jobs' => $count,
        'exit_code' => $exitCode,
        'processed' => $processed,
        'failed' => $failed,
        'remaining_jobs' => (int) DB::table('jobs')->count(),
        'webpush_queueNotification_calls' => $silent->queuedNotifications,
        'webpush_flush_calls' => $silent->flushCalls,
        'enqueue_seconds' => round($enqueueSeconds, 4),
        'process_seconds' => round($processSeconds, 4),
        'total_seconds' => round($totalSeconds, 4),
        'jobs_per_second' => $processSeconds > 0 ? round($processed / $processSeconds, 2) : 0,
        'avg_ms_per_job' => $processed > 0 ? round(($processSeconds * 1000) / $processed, 3) : 0,
        'http_sent' => false,
        'queue_driver' => (string) config('queue.default'),
        'workers' => 1,
    ];
}

function assertThroughputResult(array $row): void
{
    fwrite(STDOUT, PHP_EOL.'NOTIFICATION THROUGHPUT BENCHMARK (no HTTP)'.PHP_EOL);
    fwrite(STDOUT, json_encode($row, JSON_PRETTY_PRINT).PHP_EOL);

    expect($row['exit_code'])->toBe(0)
        ->and($row['processed'])->toBe($row['jobs'])
        ->and($row['failed'])->toBe(0)
        ->and($row['remaining_jobs'])->toBe(0)
        ->and($row['webpush_queueNotification_calls'])->toBe($row['jobs'])
        ->and($row['webpush_flush_calls'])->toBe($row['jobs'])
        ->and($row['http_sent'])->toBeFalse()
        ->and($row['queue_driver'])->toBe('database');
}

test('measures 100 queued webpush jobs without sending HTTP', function () {
    assertThroughputResult(measureNotificationThroughput(100));
})->group('benchmark');

test('measures 1000 queued webpush jobs without sending HTTP', function () {
    assertThroughputResult(measureNotificationThroughput(1000));
})->group('benchmark');
