<?php

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Jobs\MatchCustomerRequestJob;
use App\Jobs\RecoverPendingMatchingJob;
use App\Models\CustomerRequest;
use App\Models\MerchantRequestMatch;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Notifications\MatchedCustomerRequestNotification;
use App\Services\MatchedRequestPushDispatcher;
use App\Services\MerchantPermissionService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concurrency\ConcurrencyFixtures;
use Tests\Support\Concurrency\ConcurrentProcesses;

beforeEach(function () {
    if (! ConcurrentProcesses::supported()) {
        $this->markTestSkipped('pcntl_fork is required for overlapping concurrency tests.');
    }

    app(MerchantPermissionService::class)->seedCatalog();
});

test('overlapping match jobs cannot create duplicate live or history rows', function () {
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $merchant = ConcurrencyFixtures::merchantForCategory($category);
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $requestId = (int) $request->id;

    ConcurrentProcesses::map(2, function () use ($requestId) {
        app()->call([new MatchCustomerRequestJob($requestId), 'handle']);

        return RequestMatch::query()->where('customer_request_id', $requestId)->count();
    });

    $fresh = $request->fresh();

    expect(RequestMatch::query()->where('customer_request_id', $requestId)->count())->toBe(1)
        ->and(RequestMatch::query()->where('customer_request_id', $requestId)->where('merchant_id', $merchant->id)->count())->toBe(1)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $requestId)->where('merchant_id', $merchant->id)->count())->toBe(1)
        ->and($fresh->matching_completed_at)->not->toBeNull();
});

test('recovery and a live match job cannot double-complete or duplicate rows', function () {
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $merchant = ConcurrencyFixtures::merchantForCategory($category);
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $request->matching_last_attempt_at = now()->subMinutes(CustomerRequestPipelineConfig::matchingRecoveryStaleMinutes() + 5);
    $request->save();
    $requestId = (int) $request->id;

    ConcurrentProcesses::map(2, function (int $index) use ($requestId) {
        if ($index === 0) {
            app()->call([new RecoverPendingMatchingJob, 'handle']);
        } else {
            app()->call([new MatchCustomerRequestJob($requestId), 'handle']);
        }

        return $requestId;
    });

    $fresh = $request->fresh();

    expect(RequestMatch::query()->where('customer_request_id', $requestId)->count())->toBe(1)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $requestId)->where('merchant_id', $merchant->id)->count())->toBe(1)
        ->and($fresh->matching_completed_at)->not->toBeNull();
});

test('overlapping rematch cannot clear an existing matching completion marker', function () {
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    ConcurrencyFixtures::merchantForCategory($category);
    $completedAt = now()->subMinute()->startOfSecond();
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $request->matching_completed_at = $completedAt;
    $request->matching_last_attempt_at = now()->subMinutes(2);
    $request->save();
    $requestId = (int) $request->id;

    ConcurrentProcesses::map(2, function () use ($requestId) {
        app()->call([new MatchCustomerRequestJob($requestId), 'handle']);

        return CustomerRequest::query()->findOrFail($requestId)->matching_completed_at?->toIso8601String();
    });

    $fresh = $request->fresh();

    expect($fresh->matching_completed_at)->not->toBeNull()
        ->and($fresh->matching_completed_at->getTimestamp())->toBe($completedAt->getTimestamp());
});

test('same match and user is notified at most once within the idempotency window', function () {
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $merchant = ConcurrencyFixtures::merchantForCategory($category);
    $owner = User::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $owner->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    Notification::fake();
    app()->call([new MatchCustomerRequestJob((int) $request->id), 'handle']);

    $match = RequestMatch::query()->where('customer_request_id', $request->id)->firstOrFail();
    $matchId = (int) $match->id;
    $userId = (int) $owner->id;
    Cache::forget("matched-request-notification:{$matchId}:{$userId}");

    $results = ConcurrentProcesses::map(2, function () use ($matchId, $userId) {
        Notification::fake();
        app(MatchedRequestPushDispatcher::class)->notify([$matchId]);

        $user = User::query()->findOrFail($userId);

        return Notification::sent($user, MatchedCustomerRequestNotification::class)->count();
    });

    ConcurrentProcesses::assertAllOk($results);

    $sent = array_sum(ConcurrentProcesses::values($results));

    expect($sent)->toBe(1)
        ->and(Cache::has("matched-request-notification:{$matchId}:{$userId}"))->toBeTrue();
});
