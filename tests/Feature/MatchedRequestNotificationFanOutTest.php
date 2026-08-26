<?php

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\RequestMatches\Status as MatchStatus;
use App\Enums\Users\Status as UserStatus;
use App\Jobs\DispatchMatchedRequestNotifications;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantRequestMatch;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Notifications\CustomerOfferReceivedNotification;
use App\Notifications\MatchedCustomerRequestNotification;
use App\Services\MatchedRequestPushDispatcher;
use App\Services\MatchedRequestRecipientResolver;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function fanOutMembership(User $user, Merchant $merchant, Role $role = Role::Staff, MembershipStatus $status = MembershipStatus::Active): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

function fanOutMatchedSetup(?User $user = null, Role $role = Role::Owner): array
{
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    $user ??= User::factory()->create(['status' => UserStatus::Active]);
    $membership = fanOutMembership($user, $merchant, $role);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'Need an ABS sensor',
    ]);

    return compact('category', 'merchant', 'user', 'membership', 'request');
}

/**
 * @return array{request: CustomerRequest, matchIds: list<int>, userIds: list<int>}
 */
function fanOutBulkMatches(int $count): array
{
    static $batch = 0;
    $batch++;
    $token = 'fanout-'.$batch;

    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);

    $now = now();
    $password = Hash::make('password');
    $userRows = [];
    $merchantRows = [];

    for ($i = 1; $i <= $count; $i++) {
        $userRows[] = [
            'name' => "Fanout {$token} {$i}",
            'email' => "{$token}-{$i}@invalid.example",
            'phone' => sprintf('018%07d', ($batch * 10000) + $i),
            'password' => $password,
            'status' => UserStatus::Active->value,
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $merchantRows[] = [
            'public_id' => (string) Str::ulid(),
            'name' => "Fanout Merchant {$token} {$i}",
            'status' => MerchantStatus::Active->value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($userRows, 200) as $chunk) {
        User::query()->insert($chunk);
    }
    foreach (array_chunk($merchantRows, 200) as $chunk) {
        Merchant::query()->insert($chunk);
    }

    $users = User::query()->where('email', 'like', $token.'-%@invalid.example')->orderBy('id')->get();
    $merchants = Merchant::query()->where('name', 'like', "Fanout Merchant {$token} %")->orderBy('id')->get();

    $membershipRows = [];
    $matchRows = [];
    $historyRows = [];
    $categoryRows = [];

    foreach ($users as $index => $user) {
        $merchant = $merchants[$index];
        $membershipRows[] = [
            'merchant_id' => $merchant->id,
            'user_id' => $user->id,
            'role' => Role::Owner->value,
            'status' => MembershipStatus::Active->value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $matchRows[] = [
            'customer_request_id' => $request->id,
            'merchant_id' => $merchant->id,
            'status' => MatchStatus::Pending->value,
            'matched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $historyRows[] = [
            'merchant_id' => $merchant->id,
            'customer_request_id' => $request->id,
            'matched_category_id' => $category->id,
            'matched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $categoryRows[] = [
            'merchant_id' => $merchant->id,
            'category_id' => $category->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($membershipRows, 200) as $chunk) {
        MerchantUser::query()->insert($chunk);
    }
    foreach (array_chunk($matchRows, 200) as $chunk) {
        RequestMatch::query()->insert($chunk);
    }
    foreach (array_chunk($historyRows, 200) as $chunk) {
        MerchantRequestMatch::query()->insert($chunk);
    }
    foreach (array_chunk($categoryRows, 200) as $chunk) {
        MerchantCategory::query()->insert($chunk);
    }

    $matchIds = RequestMatch::query()
        ->where('customer_request_id', $request->id)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    return [
        'request' => $request,
        'matchIds' => $matchIds,
        'userIds' => $users->pluck('id')->map(fn ($id) => (int) $id)->all(),
    ];
}

test('customer request matching still creates one live match per eligible merchant', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $first = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $second = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $inactive = Merchant::factory()->create(['status' => MerchantStatus::Inactive]);
    MerchantCategory::factory()->create(['merchant_id' => $first->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $second->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $inactive->id, 'category_id' => $category->id]);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);

    $result = app(RequestMatchingService::class)->sync($request);

    expect($result['created'])->toBe(2)
        ->and($result['created_match_ids'])->toHaveCount(2)
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(RequestMatch::query()->where('merchant_id', $inactive->id)->exists())->toBeFalse()
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->pluck('matched_category_id')->unique()->all())
        ->toBe([$category->id]);
});

test('http matching path dispatches one orchestration job instead of recipient jobs', function () {
    Queue::fake();
    ['request' => $request] = fanOutMatchedSetup();

    $result = app(RequestMatchingService::class)->sync($request);

    expect($result['created'])->toBe(1);
    Queue::assertPushed(DispatchMatchedRequestNotifications::class, 1);
    Queue::assertPushed(DispatchMatchedRequestNotifications::class, function (DispatchMatchedRequestNotifications $job) use ($request, $result) {
        return $job->customerRequestId === (int) $request->id
            && $job->matchIds === $result['created_match_ids'];
    });
});

test('orchestration job queues notifications for eligible recipients only', function () {
    Notification::fake();
    ['user' => $owner, 'merchant' => $merchant, 'request' => $request] = fanOutMatchedSetup();
    $staff = User::factory()->create(['status' => UserStatus::Active]);
    fanOutMembership($staff, $merchant, Role::Staff);

    $inactiveMember = User::factory()->create(['status' => UserStatus::Active]);
    fanOutMembership($inactiveMember, $merchant, Role::Staff, MembershipStatus::Inactive);

    $inactiveAccount = User::factory()->create(['status' => UserStatus::Inactive]);
    fanOutMembership($inactiveAccount, $merchant, Role::Staff);

    $noView = User::factory()->create(['status' => UserStatus::Active]);
    $noViewMembership = fanOutMembership($noView, $merchant, Role::Staff);
    app(MerchantPermissionService::class)->syncPermissions($noViewMembership, [
        PermissionKey::TeamView->value,
        PermissionKey::MerchantProfileView->value,
    ], log: false);

    app(RequestMatchingService::class)->sync($request);

    Notification::assertSentTo($owner, MatchedCustomerRequestNotification::class);
    Notification::assertSentTo($staff, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($inactiveMember, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($inactiveAccount, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($noView, MatchedCustomerRequestNotification::class);
    Notification::assertSentTimes(MatchedCustomerRequestNotification::class, 2);
});

test('rerun and orchestration retry do not duplicate recipient notifications', function () {
    Notification::fake();
    ['user' => $user, 'request' => $request] = fanOutMatchedSetup();

    $result = app(RequestMatchingService::class)->sync($request);
    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);

    $rerun = app(RequestMatchingService::class)->sync($request->fresh());
    expect($rerun['created'])->toBe(0);
    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);

    $job = new DispatchMatchedRequestNotifications((int) $request->id, $result['created_match_ids']);
    $job->handle(app(MatchedRequestPushDispatcher::class));
    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);
});

test('one thousand matched recipients are processed in configured chunks', function () {
    Notification::fake();
    config(['notifications.matched_request_chunk_size' => 200]);

    $seeded = fanOutBulkMatches(1000);
    $chunks = 0;
    $original = app(MatchedRequestRecipientResolver::class);
    $this->app->instance(MatchedRequestRecipientResolver::class, new class($original->merchantPermissionService) extends MatchedRequestRecipientResolver
    {
        public int $chunks = 0;

        public function usersGroupedByMerchantId(array $merchantIds): Collection
        {
            $this->chunks++;

            return parent::usersGroupedByMerchantId($merchantIds);
        }
    });

    $job = new DispatchMatchedRequestNotifications((int) $seeded['request']->id, $seeded['matchIds']);
    $job->handle(app(MatchedRequestPushDispatcher::class));

    $resolver = app(MatchedRequestRecipientResolver::class);
    expect($resolver->chunks)->toBe(5)
        ->and($seeded['matchIds'])->toHaveCount(1000);
    Notification::assertSentTimes(MatchedCustomerRequestNotification::class, 1000);
});

test('matched notification retry and backoff are configured', function () {
    $match = RequestMatch::factory()->create();
    $notification = new MatchedCustomerRequestNotification($match);
    $job = new DispatchMatchedRequestNotifications((int) $match->customer_request_id, [(int) $match->id]);

    expect($notification->tries)->toBe(3)
        ->and($notification->backoff)->toBe([10, 30, 60])
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60]);
});

test('recipient discovery query count stays flat between 10 and 100 matches', function () {
    Notification::fake();
    config(['notifications.matched_request_chunk_size' => 200]);

    $measure = function (int $count): int {
        $seeded = fanOutBulkMatches($count);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(MatchedRequestPushDispatcher::class)->notify($seeded['matchIds'], (int) $seeded['request']->id);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $queries10 = $measure(10);
    $queries100 = $measure(100);

    fwrite(STDOUT, PHP_EOL."recipient discovery queries: 10 matches={$queries10}, 100 matches={$queries100}".PHP_EOL);

    expect($queries10)->toBeGreaterThan(0)
        ->and($queries100)->toBeLessThanOrEqual($queries10 + 2)
        ->and($queries100)->toBeLessThan(20)
        ->and($queries100 / max(1, $queries10))->toBeLessThan(2);
});

test('offer notifications remain a separate dispatcher', function () {
    $source = file_get_contents(app_path('Services/CustomerOfferPushDispatcher.php'));
    $offerNotification = file_get_contents(app_path('Notifications/CustomerOfferReceivedNotification.php'));

    expect($source)->toContain('CustomerOfferReceivedNotification')
        ->and($source)->not->toContain('DispatchMatchedRequestNotifications')
        ->and($offerNotification)->not->toContain('public int $tries')
        ->and(class_exists(CustomerOfferReceivedNotification::class))->toBeTrue();
});
