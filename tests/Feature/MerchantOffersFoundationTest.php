<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use App\Support\MerchantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function offerSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function offerEstablish(User $user, Merchant $merchant): void
{
    $membership = MerchantUser::query()
        ->where('user_id', $user->id)
        ->where('merchant_id', $merchant->id)
        ->firstOrFail();

    app(MerchantContext::class)->set($merchant, $membership);
}

function matchedOfferSetup(Role $role = Role::Owner): array
{
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $user = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => MembershipStatus::Active,
    ]);

    $customer = Customer::factory()->create();
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'Need Honor X9 screen',
    ]);

    app(RequestMatchingService::class)->sync($request);

    return compact('category', 'merchant', 'user', 'customer', 'request');
}

function offerPayload(array $overrides = []): array
{
    return array_merge([
        'price' => '32.500',
        'availability_status' => AvailabilityStatus::Available->value,
        'notes' => 'Original screen, 3 months warranty',
        'valid_until' => now()->addDays(7)->toDateString(),
    ], $overrides);
}

test('matched merchant can submit one offer with hashed-safe money and OMR', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), array_merge(offerPayload(), [
            'merchant_id' => 999,
            'currency' => 'USD',
        ]))
        ->assertSessionHasErrors(['merchant_id', 'currency']);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect(route('merchant.requests.show', $request));

    $offer = MerchantOffer::query()->first();

    expect($offer)->not->toBeNull()
        ->and($offer->customer_request_id)->toBe($request->id)
        ->and($offer->merchant_id)->toBe($merchant->id)
        ->and((string) $offer->price)->toBe('32.500')
        ->and($offer->currency)->toBe('OMR')
        ->and($offer->status)->toBe(OfferStatus::Submitted)
        ->and($offer->submitted_at)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeFalse();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload(['price' => '40.000']))
        ->assertSessionHasErrors('price');

    expect(MerchantOffer::query()->count())->toBe(1);
});

test('unmatched dismissed inactive merchant or membership cannot submit', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'category' => $category] = matchedOfferSetup();

    $outsider = Merchant::factory()->create();
    $outsiderUser = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $outsiderUser->id,
        'merchant_id' => $outsider->id,
        'role' => Role::Owner,
    ]);
    MerchantCategory::factory()->create([
        'merchant_id' => $outsider->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($outsiderUser)
        ->withSession(offerSession($outsider))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.dismiss', $request))
        ->assertRedirect();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertForbidden();

    $inactiveMerchant = Merchant::factory()->create(['status' => MerchantStatus::Inactive]);
    $inactiveUser = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $inactiveUser->id,
        'merchant_id' => $inactiveMerchant->id,
        'role' => Role::Owner,
    ]);

    $this->actingAs($inactiveUser)
        ->withSession(offerSession($inactiveMerchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    $inactiveMemberUser = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $inactiveMemberUser->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Staff,
        'status' => MembershipStatus::Inactive,
    ]);

    $this->actingAs($inactiveMemberUser)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();
});

test('closed and cancelled requests reject offers', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();

    $request->status = RequestStatus::Closed;
    $request->save();
    app(RequestMatchingService::class)->sync($request);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertForbidden();

    ['merchant' => $merchant2, 'user' => $user2, 'request' => $request2] = matchedOfferSetup();
    $request2->status = RequestStatus::Cancelled;
    $request2->save();
    app(RequestMatchingService::class)->sync($request2);

    $this->actingAs($user2)
        ->withSession(offerSession($merchant2))
        ->post(route('merchant.requests.offers.store', $request2), offerPayload())
        ->assertForbidden();
});

test('owner manager staff offer permission defaults and grants', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $staff = User::factory()->create();
    MerchantUser::factory()->create(['user_id' => $owner->id, 'merchant_id' => $merchant->id, 'role' => Role::Owner]);
    $managerMembership = MerchantUser::factory()->create(['user_id' => $manager->id, 'merchant_id' => $merchant->id, 'role' => Role::Manager]);
    $staffMembership = MerchantUser::factory()->create(['user_id' => $staff->id, 'merchant_id' => $merchant->id, 'role' => Role::Staff]);

    offerEstablish($owner, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::OffersCreate->value))->toBeTrue()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::OffersUpdate->value))->toBeTrue()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::OffersWithdraw->value))->toBeTrue();

    offerEstablish($manager, $merchant);
    expect($managerMembership->permissions()->pluck('key')->all())->toEqualCanonicalizing(array_map(
        fn ($key) => $key->value,
        PermissionKey::managerDefaults()
    ));

    offerEstablish($staff, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::OffersView->value))->toBeTrue()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::OffersCreate->value))->toBeFalse()
        ->and($staff->hasRole('admin'))->toBeFalse();

    ['request' => $request, 'category' => $category] = matchedOfferSetup();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    $linkedRequest = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($linkedRequest);

    $this->actingAs($staff)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $linkedRequest), offerPayload())
        ->assertForbidden();

    app(MerchantPermissionService::class)->syncPermissions($staffMembership, [
        ...array_map(fn ($key) => $key->value, PermissionKey::staffDefaults()),
        PermissionKey::OffersCreate->value,
    ], log: false);

    $this->actingAs($staff)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $linkedRequest), offerPayload())
        ->assertRedirect();
});

test('merchant can update and withdraw own offer only', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'category' => $category] = matchedOfferSetup();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    $offer = MerchantOffer::query()->first();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.update', $request), offerPayload(['price' => '40.250', 'notes' => 'Updated']))
        ->assertRedirect();

    expect((string) $offer->fresh()->price)->toBe('40.250')
        ->and($offer->fresh()->notes)->toBe('Updated');

    $other = Merchant::factory()->create();
    $otherUser = User::factory()->create();
    MerchantUser::factory()->create(['user_id' => $otherUser->id, 'merchant_id' => $other->id, 'role' => Role::Owner]);
    MerchantCategory::factory()->create(['merchant_id' => $other->id, 'category_id' => $category->id]);
    app(RequestMatchingService::class)->sync($request);

    $this->actingAs($otherUser)
        ->withSession(offerSession($other))
        ->post(route('merchant.requests.offers.update', $request), offerPayload(['price' => '1.000']))
        ->assertNotFound();

    expect((string) $offer->fresh()->price)->toBe('40.250');

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.withdraw', $request))
        ->assertRedirect();

    expect($offer->fresh()->status)->toBe(OfferStatus::Withdrawn)
        ->and($offer->fresh()->withdrawn_at)->not->toBeNull()
        ->and(MerchantOffer::query()->count())->toBe(1);
});

test('offer images are private raster-only with a max of five', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'customer' => $customer] = matchedOfferSetup();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), array_merge(offerPayload(), [
            'images' => [UploadedFile::fake()->create('bad.svg', 20, 'image/svg+xml')],
        ]))
        ->assertSessionHasErrors();

    expect(MerchantOffer::query()->count())->toBe(0);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    expect(MerchantOffer::query()->first()->images()->count())->toBe(0);

    MerchantOffer::query()->delete();

    $files = [];
    for ($i = 0; $i < 5; $i++) {
        $files[] = UploadedFile::fake()->image("offer{$i}.jpg");
    }

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), array_merge(offerPayload(), [
            'images' => $files,
        ]))
        ->assertRedirect();

    $offer = MerchantOffer::query()->with('images')->first();
    expect($offer->images)->toHaveCount(5);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.update', $request), array_merge(offerPayload(), [
            'images' => [UploadedFile::fake()->image('sixth.jpg')],
        ]))
        ->assertSessionHasErrors('images');

    $image = $offer->images->first();
    $path = $image->getRawOriginal('path') ?? $image->path;

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertOk();

    $otherMerchant = Merchant::factory()->create();
    $otherUser = User::factory()->create();
    MerchantUser::factory()->create(['user_id' => $otherUser->id, 'merchant_id' => $otherMerchant->id, 'role' => Role::Owner]);

    $this->actingAs($otherUser)
        ->withSession(offerSession($otherMerchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertForbidden();

    $customerUser = User::factory()->create();
    $customer->user_id = $customerUser->id;
    $customer->save();

    $this->actingAs($customerUser)
        ->get(route('customer.offers.images.show', [$offer, $image]))
        ->assertOk();

    $stranger = User::factory()->create();
    Customer::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->get(route('customer.offers.images.show', [$offer, $image]))
        ->assertForbidden();

    Storage::disk('local')->assertExists($path);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.update', $request), array_merge(offerPayload(), [
            'remove_image_ids' => [$image->id],
        ]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($path);
    expect(MerchantOfferImage::query()->whereKey($image->id)->exists())->toBeFalse();
});

test('customer sees only own submitted offers and counts', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'customer' => $customer] = matchedOfferSetup();
    $customerUser = User::factory()->create();
    $customer->user_id = $customerUser->id;
    $customer->email = $customerUser->email;
    $customer->save();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    $otherCustomer = Customer::factory()->create();
    $otherUser = User::factory()->create();
    $otherCustomer->user_id = $otherUser->id;
    $otherCustomer->save();
    $otherRequest = CustomerRequest::factory()->create([
        'customer_id' => $otherCustomer->id,
        'category_id' => $request->category_id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($otherRequest);
    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $otherRequest), offerPayload())
        ->assertRedirect();

    $this->actingAs($customerUser)
        ->get(route('customer.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestsIndexPage', false)
            ->where('requests.data.0.submitted_offers_count', 1)
        );

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestShowPage', false)
            ->has('offers', 1)
            ->where('offers.0.merchant_name', $merchant->name)
            ->where('offers.0.price', '32.500')
            ->missing('offers.0.merchant.users')
            ->missing('request.customer.phone')
        );

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $otherRequest))
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.withdraw', $request));

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));
});

test('stale match invalidates offer without deleting it or changing matching rules', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'category' => $category] = matchedOfferSetup();
    $customerUser = User::factory()->create();
    $request->customer->user_id = $customerUser->id;
    $request->customer->save();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    $otherCategory = Category::factory()->create();
    $request->category_id = $otherCategory->id;
    $request->save();
    $result = app(RequestMatchingService::class)->sync($request);

    $offer = MerchantOffer::query()->first();

    expect($offer->status)->toBe(OfferStatus::Invalidated)
        ->and(MerchantOffer::query()->count())->toBe(1)
        ->and(RequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $request->id)->exists())->toBeFalse()
        ->and($result['removed'])->toBeGreaterThan(0);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.update', $request), offerPayload(['price' => '11.000']))
        ->assertForbidden();

    $eligible = Merchant::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $eligible->id,
        'category_id' => $otherCategory->id,
    ]);
    $sync = app(RequestMatchingService::class)->sync($request->fresh());
    expect($sync['created'])->toBe(1)
        ->and(RequestMatch::query()->where('merchant_id', $eligible->id)->exists())->toBeTrue();
});

test('merchant request payload does not expose customer contact fields', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'customer' => $customer] = matchedOfferSetup();
    $customer->phone = '0100999888';
    $customer->email = 'hidden-customer@example.test';
    $customer->whatsapp_id = 'wa-hidden';
    $customer->save();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->get(route('merchant.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantRequestShowPage', false)
            ->missing('request.customer')
            ->where('request.request_text', 'Need Honor X9 screen')
        );

    $content = $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->get(route('merchant.requests.show', $request))
        ->getContent();

    expect($content)->not->toContain('0100999888')
        ->and($content)->not->toContain('hidden-customer@example.test')
        ->and($content)->not->toContain('wa-hidden');
});
