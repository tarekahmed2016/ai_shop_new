<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\User;
use App\Notifications\CustomerOfferReceivedNotification;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use App\Support\MerchantContext;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
    Notification::fake();
});

function ownershipPushPayload(string $endpoint, array $keys = ['p256dh' => 'pk', 'auth' => 'ak']): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => $keys,
        'contentEncoding' => 'aes128gcm',
    ];
}

function ownershipMerchantSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function ownershipLinkedCustomer(?User $user = null): array
{
    $user ??= User::factory()->create(['status' => UserStatus::Active]);
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
        'email' => $user->email,
        'phone' => $user->phone,
    ]);

    return compact('user', 'customer');
}

function ownershipMerchantUser(?User $user = null): array
{
    $user ??= User::factory()->create(['status' => UserStatus::Active]);
    $merchant = Merchant::factory()->create();
    $membership = MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);
    app(MerchantContext::class)->set($merchant, $membership);

    return compact('user', 'merchant', 'membership');
}

test('re-synchronizing the same endpoint keeps a single row for the same user', function () {
    ['user' => $user, 'merchant' => $merchant] = ownershipMerchantUser();

    $this->actingAs($user)
        ->withSession(ownershipMerchantSession($merchant))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk()
        ->assertJson(['subscribed' => true])
        ->assertJsonMissingPath('endpoint')
        ->assertJsonMissingPath('vapid_private_key')
        ->assertJsonMissingPath('public_key')
        ->assertJsonMissingPath('auth_token');

    $this->actingAs($user)
        ->withSession(ownershipMerchantSession($merchant))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc', [
            'p256dh' => 'pk-updated',
            'auth' => 'ak-updated',
        ]))
        ->assertOk()
        ->assertJson(['subscribed' => true]);

    $rows = PushSubscription::query()->where('endpoint', 'https://push.example.com/abc')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->subscribable_id)->toBe($user->id)
        ->and($rows->first()->subscribable_type)->toBe($user->getMorphClass())
        ->and($rows->first()->public_key)->toBe('pk-updated');
});

test('account switch reassigns the unique endpoint to the newly authenticated user', function () {
    ['user' => $userA, 'merchant' => $merchantA] = ownershipMerchantUser();
    ['user' => $userB, 'customer' => $customerB] = ownershipLinkedCustomer();

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    $this->actingAs($userB)
        ->postJson(route('customer.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk()
        ->assertJson(['subscribed' => true]);

    $rows = PushSubscription::query()->where('endpoint', 'https://push.example.com/abc')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->subscribable_id)->toBe($userB->id)
        ->and($userA->fresh()->pushSubscriptions()->where('endpoint', 'https://push.example.com/abc')->count())->toBe(0)
        ->and($userB->fresh()->pushSubscriptions()->where('endpoint', 'https://push.example.com/abc')->count())->toBe(1);
});

test('reassigned endpoint delivers customer offer push to the new owner only', function () {
    ['user' => $userA, 'merchant' => $merchantA] = ownershipMerchantUser();
    ['user' => $userB, 'customer' => $customerB] = ownershipLinkedCustomer();

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    $this->actingAs($userB)
        ->postJson(route('customer.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    $category = Category::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $merchantA->id,
        'category_id' => $category->id,
    ]);
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customerB->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($request);

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->post(route('merchant.requests.offers.store', $request), [
            'price' => '12.000',
            'availability_status' => AvailabilityStatus::Available->value,
            'notes' => 'Screen',
            'valid_until' => now()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    Notification::assertSentTo($userB, CustomerOfferReceivedNotification::class);
    Notification::assertNotSentTo($userA, CustomerOfferReceivedNotification::class);

    $endpointsForB = $userB->fresh()->routeNotificationForWebPush()->pluck('endpoint')->all();
    $endpointsForA = $userA->fresh()->routeNotificationForWebPush()->pluck('endpoint')->all();
    expect($endpointsForB)->toContain('https://push.example.com/abc')
        ->and($endpointsForA)->not->toContain('https://push.example.com/abc');
});

test('the same centralized user does not duplicate an endpoint across merchant and customer portals', function () {
    ['user' => $user, 'merchant' => $merchant] = ownershipMerchantUser();
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
        'email' => $user->email,
        'phone' => $user->phone,
    ]);

    $this->actingAs($user)
        ->withSession(ownershipMerchantSession($merchant))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('customer.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    expect(PushSubscription::query()->where('endpoint', 'https://push.example.com/abc')->count())->toBe(1)
        ->and(PushSubscription::query()->where('endpoint', 'https://push.example.com/abc')->value('subscribable_id'))
        ->toBe($user->id)
        ->and($user->fresh()->pushSubscriptions()->count())->toBe(1);
});

test('reassigning one device endpoint leaves the previous user other devices intact', function () {
    ['user' => $userA, 'merchant' => $merchantA] = ownershipMerchantUser();
    ['user' => $userB] = ownershipLinkedCustomer();

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/xyz'))
        ->assertOk();

    $this->actingAs($userB)
        ->postJson(route('customer.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    expect(PushSubscription::query()->count())->toBe(2)
        ->and(PushSubscription::query()->where('endpoint', 'https://push.example.com/abc')->value('subscribable_id'))->toBe($userB->id)
        ->and(PushSubscription::query()->where('endpoint', 'https://push.example.com/xyz')->value('subscribable_id'))->toBe($userA->id);
});

test('forged user customer and merchant ids are rejected on both portals', function () {
    ['user' => $merchantUser, 'merchant' => $merchant] = ownershipMerchantUser();
    ['user' => $customerUser] = ownershipLinkedCustomer();

    $this->actingAs($merchantUser)
        ->withSession(ownershipMerchantSession($merchant))
        ->postJson(route('merchant.push-subscriptions.store'), array_merge(ownershipPushPayload('https://push.example.com/abc'), [
            'user_id' => 999,
            'customer_id' => 888,
            'merchant_id' => 777,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'customer_id', 'merchant_id']);

    $this->actingAs($customerUser)
        ->postJson(route('customer.push-subscriptions.store'), array_merge(ownershipPushPayload('https://push.example.com/def'), [
            'user_id' => 999,
            'customer_id' => 888,
            'merchant_id' => 777,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'customer_id', 'merchant_id']);
});

test('unsubscribe cannot remove another users unrelated subscription', function () {
    ['user' => $userA, 'merchant' => $merchantA] = ownershipMerchantUser();
    ['user' => $userB] = ownershipLinkedCustomer();

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->postJson(route('merchant.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/xyz'))
        ->assertOk();

    $this->actingAs($userB)
        ->deleteJson(route('customer.push-subscriptions.destroy'), [
            'endpoint' => 'https://push.example.com/xyz',
        ])
        ->assertOk();

    expect(PushSubscription::query()->where('endpoint', 'https://push.example.com/xyz')->value('subscribable_id'))
        ->toBe($userA->id);

    $this->actingAs($userB)
        ->postJson(route('customer.push-subscriptions.store'), ownershipPushPayload('https://push.example.com/abc'))
        ->assertOk();

    $this->actingAs($userA)
        ->withSession(ownershipMerchantSession($merchantA))
        ->deleteJson(route('merchant.push-subscriptions.destroy'), [
            'endpoint' => 'https://push.example.com/abc',
        ])
        ->assertOk();

    expect(PushSubscription::query()->where('endpoint', 'https://push.example.com/abc')->value('subscribable_id'))
        ->toBe($userB->id);
});

test('useWebPush reconciles an existing subscription without requesting permission', function () {
    $source = file_get_contents(resource_path('js/Composables/useWebPush.js'));

    expect($source)->toContain('persistSubscription')
        ->and($source)->toContain('getSubscription()')
        ->and(substr_count($source, 'requestPermission'))->toBe(1)
        ->and($source)->toContain("if (permission === 'granted' && subscription)");
});
