<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

test('expired database cache rows and locks are pruned and live rows are kept', function () {
    $now = time();

    DB::table('cache')->insert([
        ['key' => 'live-cache-key', 'value' => 'keep', 'expiration' => $now + 3600],
        ['key' => 'stale-cache-key', 'value' => 'drop', 'expiration' => $now - 10],
    ]);
    DB::table('cache_locks')->insert([
        ['key' => 'live-lock-key', 'owner' => 'owner-a', 'expiration' => $now + 3600],
        ['key' => 'stale-lock-key', 'owner' => 'owner-b', 'expiration' => $now - 10],
    ]);

    $this->artisan('cache:prune-expired')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 expired cache row(s) and 1 expired lock(s).');

    expect(DB::table('cache')->pluck('key')->all())->toBe(['live-cache-key'])
        ->and(DB::table('cache_locks')->pluck('key')->all())->toBe(['live-lock-key']);
});

test('expired cache prune is a no-op when tables are empty', function () {
    $this->artisan('cache:prune-expired')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 0 expired cache row(s) and 0 expired lock(s).');
});

test('queue batch failed-job and expired-cache pruning are scheduled daily', function () {
    $events = collect(app(Schedule::class)->events());

    $batches = $events->first(
        fn ($scheduled) => str_contains((string) $scheduled->command, 'queue:prune-batches'),
    );
    $failed = $events->first(
        fn ($scheduled) => str_contains((string) $scheduled->command, 'queue:prune-failed'),
    );
    $cache = $events->first(
        fn ($scheduled) => str_contains((string) $scheduled->command, 'cache:prune-expired'),
    );

    expect($batches)->not->toBeNull()
        ->and($batches->expression)->toBe('0 0 * * *')
        ->and((string) $batches->command)->toContain('--hours=48')
        ->and($failed)->not->toBeNull()
        ->and($failed->expression)->toBe('0 0 * * *')
        ->and((string) $failed->command)->toContain('--hours=168')
        ->and($cache)->not->toBeNull()
        ->and($cache->expression)->toBe('0 0 * * *');
});
