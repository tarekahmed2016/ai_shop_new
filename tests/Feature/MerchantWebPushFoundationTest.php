<?php

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Jobs\DispatchMatchedRequestNotifications;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Notifications\MatchedCustomerRequestNotification;
use App\Services\MatchedRequestPushDispatcher;
use App\Services\MatchedRequestRecipientResolver;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;
use NotificationChannels\WebPush\PushSubscription;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
    Notification::fake();
});

function pushMembership(User $user, Merchant $merchant, Role $role = Role::Staff, MembershipStatus $status = MembershipStatus::Active): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

function pushMatchedSetup(?User $user = null, Role $role = Role::Owner): array
{
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    $user ??= User::factory()->create(['status' => UserStatus::Active]);
    $membership = pushMembership($user, $merchant, $role);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'Need ABS sensor secret-customer@example.com 0100999888',
    ]);

    return compact('category', 'merchant', 'user', 'membership', 'request');
}

test('user model uses webpush subscription trait', function () {
    expect(class_uses_recursive(User::class))->toContain(HasPushSubscriptions::class);
});

test('service worker showNotification for matched requests is not silent', function () {
    $script = file_get_contents(public_path('sw.js'));

    expect($script)->toContain('showNotification')
        ->and($script)->toContain('silent: false')
        ->and($script)->toContain('customer_offer_received')
        ->and($script)->toContain('matched_request')
        ->and($script)->not->toContain('silent: true');
});

test('push subscription endpoints require authentication and merchant context', function () {
    $this->postJson(route('merchant.push-subscriptions.store'), [
        'endpoint' => 'https://push.example.com/a',
        'keys' => ['p256dh' => 'pk', 'auth' => 'ak'],
    ])->assertUnauthorized();

    $plain = User::factory()->create();
    $this->actingAs($plain)
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/a',
            'keys' => ['p256dh' => 'pk', 'auth' => 'ak'],
        ])
        ->assertRedirect(route('merchant.select'));
});

test('authenticated merchant can save update and remove multiple subscriptions', function () {
    ['user' => $user, 'merchant' => $merchant] = pushMatchedSetup();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/iphone',
            'keys' => ['p256dh' => 'iphone-p256', 'auth' => 'iphone-auth'],
            'contentEncoding' => 'aes128gcm',
            'merchant_id' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('merchant_id');

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/iphone',
            'keys' => ['p256dh' => 'iphone-p256', 'auth' => 'iphone-auth'],
            'contentEncoding' => 'aes128gcm',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/desktop',
            'keys' => ['p256dh' => 'desk-p256', 'auth' => 'desk-auth'],
        ])
        ->assertOk();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/iphone',
            'keys' => ['p256dh' => 'iphone-p256-updated', 'auth' => 'iphone-auth-updated'],
        ])
        ->assertOk();

    expect($user->pushSubscriptions()->count())->toBe(2)
        ->and($user->pushSubscriptions()->where('endpoint', 'https://push.example.com/iphone')->value('public_key'))
        ->toBe('iphone-p256-updated');

    $other = User::factory()->create();
    pushMembership($other, $merchant, Role::Staff);
    $this->actingAs($other)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->deleteJson(route('merchant.push-subscriptions.destroy'), [
            'endpoint' => 'https://push.example.com/iphone',
        ])
        ->assertOk();

    expect($user->fresh()->pushSubscriptions()->count())->toBe(2);

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->deleteJson(route('merchant.push-subscriptions.destroy'), [
            'endpoint' => 'https://push.example.com/iphone',
        ])
        ->assertOk();

    expect($user->fresh()->pushSubscriptions()->pluck('endpoint')->all())
        ->toBe(['https://push.example.com/desktop']);
});

test('invalid subscription payloads are rejected', function () {
    ['user' => $user, 'merchant' => $merchant] = pushMatchedSetup();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'not-a-url',
            'keys' => ['p256dh' => '', 'auth' => ''],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

test('pending classification does not dispatch matched request notifications', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    $user = User::factory()->create();
    pushMembership($user, $merchant, Role::Owner);
    $pending = CustomerRequest::factory()->create([
        'category_id' => null,
        'status' => RequestStatus::PendingClassification,
    ]);

    $result = app(RequestMatchingService::class)->sync($pending);

    expect($result['created'])->toBe(0)
        ->and(RequestMatch::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('new request match notifies eligible users and rerun does not duplicate', function () {
    ['user' => $owner, 'merchant' => $merchant, 'request' => $request] = pushMatchedSetup();
    $staff = User::factory()->create();
    pushMembership($staff, $merchant, Role::Staff);

    $result = app(RequestMatchingService::class)->sync($request);

    expect($result['created'])->toBe(1)
        ->and($result['created_match_ids'])->toHaveCount(1);

    Notification::assertSentTo($owner, MatchedCustomerRequestNotification::class);
    Notification::assertSentTo($staff, MatchedCustomerRequestNotification::class);

    Notification::fake();
    $rerun = app(RequestMatchingService::class)->sync($request->fresh());

    expect($rerun['created'])->toBe(0)
        ->and($rerun['retained'])->toBe(1);
    Notification::assertNothingSent();
});

test('inactive merchant membership user and missing permission are not notified', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $inactiveMerchant = Merchant::factory()->create(['status' => MerchantStatus::Inactive]);
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $inactiveMerchant->id, 'category_id' => $category->id]);

    $owner = User::factory()->create();
    pushMembership($owner, $merchant, Role::Owner);

    $inactiveMembershipUser = User::factory()->create();
    pushMembership($inactiveMembershipUser, $merchant, Role::Staff, MembershipStatus::Inactive);

    $inactiveAccount = User::factory()->create(['status' => UserStatus::Inactive]);
    pushMembership($inactiveAccount, $merchant, Role::Staff);

    $noView = User::factory()->create();
    $noViewMembership = pushMembership($noView, $merchant, Role::Staff);
    app(MerchantPermissionService::class)->syncPermissions($noViewMembership, [
        PermissionKey::TeamView->value,
        PermissionKey::MerchantProfileView->value,
    ], log: false);

    $otherOwner = User::factory()->create();
    pushMembership($otherOwner, $inactiveMerchant, Role::Owner);

    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);

    app(RequestMatchingService::class)->sync($request);

    Notification::assertSentTo($owner, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($inactiveMembershipUser, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($inactiveAccount, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($noView, MatchedCustomerRequestNotification::class);
    Notification::assertNotSentTo($otherOwner, MatchedCustomerRequestNotification::class);
});

test('payload uses public ids and excludes customer pii', function () {
    ['user' => $user, 'merchant' => $merchant, 'request' => $request] = pushMatchedSetup();
    $request->update(['request_text' => 'Need help secret-customer@example.com 01001234567 wa-secret']);

    app(RequestMatchingService::class)->sync($request);

    Notification::assertSentTo($user, MatchedCustomerRequestNotification::class, function (MatchedCustomerRequestNotification $notification) use ($merchant, $request, $user) {
        $payload = $notification->safePayload();
        $encoded = json_encode($payload);

        expect($payload['type'])->toBe('matched_request')
            ->and($payload['request_public_id'])->toBe($request->public_id)
            ->and($payload['merchant_public_id'])->toBe($merchant->public_id)
            ->and($payload['destination_url'])->toBe(route('merchant.requests.open', [
                'merchant' => $merchant->public_id,
                'customerRequest' => $request->public_id,
            ]))
            ->and($payload['tag'])->toBe('matched-request-'.$merchant->public_id.'-'.$request->public_id)
            ->and($encoded)->not->toContain($user->email)
            ->and($encoded)->not->toContain('secret-customer@example.com')
            ->and($encoded)->not->toContain('01001234567')
            ->and($encoded)->not->toContain('wa-secret')
            ->and($encoded)->not->toContain('"id":'.$request->id)
            ->and($payload)->not->toHaveKey('provider_response_id');

        return true;
    });
});

test('multi merchant user is scoped and deep link switches context', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchantA->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchantB->id, 'category_id' => $category->id]);
    $user = User::factory()->create();
    pushMembership($user, $merchantA, Role::Owner);
    pushMembership($user, $merchantB, Role::Owner);
    $requestB = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);

    $result = app(RequestMatchingService::class)->sync($requestB);
    expect($result['created'])->toBe(2);

    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 2);

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchantA->id])
        ->get(route('merchant.requests.open', [
            'merchant' => $merchantB->public_id,
            'customerRequest' => $requestB->public_id,
        ]))
        ->assertRedirect(route('merchant.requests.show', $requestB));

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantB->id);
});

test('forged public ids cannot open a matched request', function () {
    ['user' => $user, 'merchant' => $merchant, 'request' => $request] = pushMatchedSetup();
    app(RequestMatchingService::class)->sync($request);
    $stranger = User::factory()->create();
    $otherMerchant = Merchant::factory()->create();
    pushMembership($stranger, $otherMerchant, Role::Owner);

    $this->actingAs($stranger)
        ->withSession([MerchantContextService::SESSION_KEY => $otherMerchant->id])
        ->get(route('merchant.requests.open', [
            'merchant' => $merchant->public_id,
            'customerRequest' => $request->public_id,
        ]))
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->get(route('merchant.requests.open', [
            'merchant' => $merchant->public_id,
            'customerRequest' => (string) Str::ulid(),
        ]))
        ->assertNotFound();
});

test('push dispatch failure does not roll back matching', function () {
    Queue::fake();
    ['request' => $request] = pushMatchedSetup();

    $result = app(RequestMatchingService::class)->sync($request);

    expect($result['created'])->toBe(1)
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and($request->fresh()->status)->toBe(RequestStatus::Ready);

    Queue::assertPushed(DispatchMatchedRequestNotifications::class, 1);

    $dispatcher = new class(app(MatchedRequestRecipientResolver::class)) extends MatchedRequestPushDispatcher
    {
        public function notify(array $matchIds, ?int $customerRequestId = null): void
        {
            throw new RuntimeException('simulated push failure');
        }
    };

    try {
        $dispatcher->notify($result['created_match_ids']);
        expect(false)->toBeTrue();
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('simulated push failure');
    }

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1);
});

test('guest is redirected to login for the deep link', function () {
    ['merchant' => $merchant, 'request' => $request] = pushMatchedSetup();

    $this->get(route('merchant.requests.open', [
        'merchant' => $merchant->public_id,
        'customerRequest' => $request->public_id,
    ]))->assertRedirect(route('login'));
});

test('config endpoint never exposes vapid private key', function () {
    ['user' => $user, 'merchant' => $merchant] = pushMatchedSetup();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->getJson(route('merchant.push-subscriptions.config'))
        ->assertOk()
        ->assertJsonMissingPath('vapid_private_key')
        ->assertJsonPath('vapid_public_key', (string) config('webpush.vapid.public_key', ''))
        ->assertJsonMissingPath('private_key');
});

test('push subscription rows belong to the authenticated user', function () {
    ['user' => $user, 'merchant' => $merchant] = pushMatchedSetup();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->postJson(route('merchant.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/owned',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ])
        ->assertOk();

    $row = PushSubscription::query()->first();
    expect($row->subscribable_id)->toBe($user->id)
        ->and($row->subscribable_type)->toBe($user->getMorphClass());
});
