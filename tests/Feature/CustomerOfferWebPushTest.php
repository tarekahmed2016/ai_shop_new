<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantUser;
use App\Models\User;
use App\Notifications\CustomerOfferReceivedNotification;
use App\Notifications\MatchedCustomerRequestNotification;
use App\Services\CustomerOfferPushDispatcher;
use App\Services\CustomerOfferRecipientResolver;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use App\Support\MerchantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\PushSubscription;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
    Notification::fake();
});

function customerPushSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function customerPushOfferPayload(array $overrides = []): array
{
    return array_merge([
        'price' => '32.500',
        'availability_status' => AvailabilityStatus::Available->value,
        'notes' => 'Original screen',
        'valid_until' => now()->addDays(7)->toDateString(),
    ], $overrides);
}

function customerPushSetup(array $customerUserAttrs = []): array
{
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $merchantUser = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $merchantUser->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $customerUser = User::factory()->create(array_merge([
        'status' => UserStatus::Active,
        'email' => 'owner-customer@example.com',
        'phone' => '01001230000',
    ], $customerUserAttrs));
    $customer = Customer::factory()->create([
        'user_id' => $customerUser->id,
        'name' => $customerUser->name,
        'email' => $customerUser->email,
        'phone' => $customerUser->phone,
        'whatsapp_id' => 'wa-secret-customer-'.$customerUser->id,
        'status' => CustomerStatus::Active,
    ]);
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'Need Honor X9 screen',
    ]);

    app(RequestMatchingService::class)->sync($request);

    $membership = MerchantUser::query()
        ->where('user_id', $merchantUser->id)
        ->where('merchant_id', $merchant->id)
        ->firstOrFail();
    app(MerchantContext::class)->set($merchant, $membership);

    return compact('category', 'merchant', 'merchantUser', 'customerUser', 'customer', 'request');
}

test('customer push config requires an authenticated linked customer', function () {
    $this->getJson(route('customer.push-subscriptions.config'))
        ->assertUnauthorized();

    $plain = User::factory()->create();
    $this->actingAs($plain)
        ->getJson(route('customer.push-subscriptions.config'))
        ->assertForbidden();

    $unlinked = Customer::factory()->create(['user_id' => null]);
    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->postJson(route('customer.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/c1',
            'keys' => ['p256dh' => 'pk', 'auth' => 'ak'],
            'customer_id' => $unlinked->id,
            'user_id' => $unlinked->id,
        ])
        ->assertForbidden();
});

test('linked customer can store multiple subscriptions and remove only their own', function () {
    ['customerUser' => $user] = customerPushSetup();
    $otherUser = User::factory()->create();
    Customer::factory()->create(['user_id' => $otherUser->id, 'status' => CustomerStatus::Active]);

    $this->actingAs($user)
        ->postJson(route('customer.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/iphone',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
            'customer_id' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');

    $this->actingAs($user)
        ->postJson(route('customer.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/iphone',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('customer.push-subscriptions.store'), [
            'endpoint' => 'https://push.example.com/desktop',
            'keys' => ['p256dh' => 'c', 'auth' => 'd'],
        ])
        ->assertOk();

    expect($user->pushSubscriptions()->count())->toBe(2);

    $this->actingAs($otherUser)
        ->deleteJson(route('customer.push-subscriptions.destroy'), [
            'endpoint' => 'https://push.example.com/iphone',
        ])
        ->assertOk();

    expect($user->fresh()->pushSubscriptions()->count())->toBe(2);

    $this->actingAs($user)
        ->deleteJson(route('customer.push-subscriptions.destroy'), [
            'endpoint' => 'https://push.example.com/iphone',
        ])
        ->assertOk();

    expect($user->fresh()->pushSubscriptions()->pluck('endpoint')->all())
        ->toBe(['https://push.example.com/desktop']);

    $row = PushSubscription::query()->where('endpoint', 'https://push.example.com/desktop')->first();
    expect($row->subscribable_id)->toBe($user->id);
});

test('new submitted offer notifies only the owning active linked customer', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'customerUser' => $owner, 'request' => $request] = customerPushSetup();
    $otherUser = User::factory()->create();
    Customer::factory()->create(['user_id' => $otherUser->id, 'status' => CustomerStatus::Active]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload())
        ->assertRedirect();

    Notification::assertSentTo($owner, CustomerOfferReceivedNotification::class);
    Notification::assertNotSentTo($otherUser, CustomerOfferReceivedNotification::class);
    Notification::assertNotSentTo($merchantUser, CustomerOfferReceivedNotification::class);
    Notification::assertNotSentTo($admin, CustomerOfferReceivedNotification::class);
    Notification::assertSentTo($merchantUser, MatchedCustomerRequestNotification::class);
});

test('inactive customer or inactive linked user is not notified', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'customer' => $customer, 'request' => $request] = customerPushSetup();
    $customer->update(['status' => CustomerStatus::Inactive]);

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload())
        ->assertRedirect();

    Notification::assertNotSentTo($customer->user, CustomerOfferReceivedNotification::class);

    ['merchant' => $merchant2, 'merchantUser' => $merchantUser2, 'customerUser' => $inactiveUser, 'request' => $request2] = customerPushSetup([
        'email' => 'inactive-user@example.com',
        'status' => UserStatus::Inactive,
    ]);

    $this->actingAs($merchantUser2)
        ->withSession(customerPushSession($merchant2))
        ->post(route('merchant.requests.offers.store', $request2), customerPushOfferPayload())
        ->assertRedirect();

    Notification::assertNotSentTo($inactiveUser, CustomerOfferReceivedNotification::class);
});

test('submitted edits and withdraw do not notify; resubmits do', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'customerUser' => $owner, 'request' => $request] = customerPushSetup();

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload())
        ->assertRedirect();

    Notification::assertSentToTimes($owner, CustomerOfferReceivedNotification::class, 1);

    Notification::fake();
    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.update', $request), customerPushOfferPayload(['price' => '40.000']))
        ->assertRedirect();
    Notification::assertNothingSent();

    Notification::fake();
    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.update', $request), customerPushOfferPayload(['notes' => 'Only notes']))
        ->assertRedirect();
    Notification::assertNothingSent();

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.withdraw', $request))
        ->assertRedirect();
    Notification::assertNothingSent();

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload(['price' => '41.000']))
        ->assertRedirect();
    Notification::assertSentToTimes($owner, CustomerOfferReceivedNotification::class, 1);

    $offer = MerchantOffer::query()->first();
    $offer->status = OfferStatus::Invalidated;
    $offer->save();

    Notification::fake();
    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload(['price' => '42.000']))
        ->assertRedirect();
    Notification::assertSentToTimes($owner, CustomerOfferReceivedNotification::class, 1);
});

test('failed offer transaction does not dispatch customer push', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'customerUser' => $owner, 'request' => $request] = customerPushSetup();

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), array_merge(customerPushOfferPayload(), [
            'images' => [UploadedFile::fake()->create('bad.svg', 20, 'image/svg+xml')],
        ]))
        ->assertSessionHasErrors();

    expect(MerchantOffer::query()->count())->toBe(0);
    Notification::assertNotSentTo($owner, CustomerOfferReceivedNotification::class);
});

test('customer push failure does not roll back the offer', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'request' => $request] = customerPushSetup();

    $this->app->bind(CustomerOfferPushDispatcher::class, function () {
        return new class(app(CustomerOfferRecipientResolver::class)) extends CustomerOfferPushDispatcher
        {
            public function notify(int $offerId): void
            {
                throw new RuntimeException('simulated customer push failure');
            }
        };
    });

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload())
        ->assertRedirect();

    expect(MerchantOffer::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(MerchantOffer::query()->first()->status)->toBe(OfferStatus::Submitted);
});

test('customer offer payload is public-id only and excludes pii and price', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'customerUser' => $owner, 'customer' => $customer, 'request' => $request] = customerPushSetup();

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload(['price' => '99.750']))
        ->assertRedirect();

    $offer = MerchantOffer::query()->first();

    Notification::assertSentTo($owner, CustomerOfferReceivedNotification::class, function (CustomerOfferReceivedNotification $notification) use ($offer, $request, $owner, $customer) {
        $payload = $notification->safePayload();
        $encoded = json_encode($payload);

        expect($payload['type'])->toBe('customer_offer_received')
            ->and($payload['request_public_id'])->toBe($request->public_id)
            ->and($payload['offer_public_id'])->toBe($offer->public_id)
            ->and($payload['destination_url'])->toBe(route('customer.requests.show', $request->public_id, false))
            ->and($payload['tag'])->toBe('customer-offer-'.$offer->public_id)
            ->and($encoded)->not->toContain('99.750')
            ->and($encoded)->not->toContain($owner->email)
            ->and($encoded)->not->toContain($owner->phone)
            ->and($encoded)->not->toContain('wa-secret-customer-'.$customer->user_id)
            ->and($payload['offer_public_id'])->not->toBe((string) $offer->id)
            ->and($payload['request_public_id'])->not->toBe((string) $request->id)
            ->and($payload)->not->toHaveKey('price')
            ->and($payload)->not->toHaveKey('customer_id')
            ->and($payload)->not->toHaveKey('user_id');

        return true;
    });
});

test('wrong customer cannot open the notification destination', function () {
    ['merchant' => $merchant, 'merchantUser' => $merchantUser, 'request' => $request] = customerPushSetup();
    $otherUser = User::factory()->create();
    Customer::factory()->create(['user_id' => $otherUser->id, 'status' => CustomerStatus::Active]);

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload())
        ->assertRedirect();

    $this->actingAs($otherUser)
        ->get(route('customer.requests.show', $request))
        ->assertNotFound();

    auth()->logout();
    $this->get(route('customer.requests.show', $request))
        ->assertRedirect(route('login'));
});

test('service worker handles customer offer notifications without silencing', function () {
    $script = file_get_contents(public_path('sw.js'));

    expect($script)->toContain('customer_offer_received')
        ->and($script)->toContain('matched_request')
        ->and($script)->toContain('silent: false')
        ->and($script)->not->toContain('silent: true');
});

test('unlinked historical customer does not crash offer submit', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    $merchantUser = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $merchantUser->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
    ]);
    $customer = Customer::factory()->create(['user_id' => null]);
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($request);

    $this->actingAs($merchantUser)
        ->withSession(customerPushSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), customerPushOfferPayload())
        ->assertRedirect();

    expect(MerchantOffer::query()->count())->toBe(1);
    Notification::assertSentTimes(CustomerOfferReceivedNotification::class, 0);
});
