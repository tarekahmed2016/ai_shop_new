<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function profileSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function profileMembership(User $user, Merchant $merchant, Role $role = Role::Owner): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => MembershipStatus::Active,
    ]);
}

test('owner can view and update business profile without changing user account', function () {
    $merchant = Merchant::factory()->create([
        'name' => 'Old Shop',
        'email' => 'shop@example.test',
        'phone' => '90000000',
    ]);
    $owner = User::factory()->create([
        'name' => 'Owner Person',
        'email' => 'owner-login@example.test',
        'phone' => '999000111',
    ]);
    profileMembership($owner, $merchant, Role::Owner);

    $this->actingAs($owner)
        ->withSession(profileSession($merchant))
        ->get(route('merchant.business-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantBusinessProfilePage', false)
            ->where('merchant.name', 'Old Shop')
            ->where('merchant.phone', '90000000')
            ->where('canUpdate', true)
        );

    $this->actingAs($owner)
        ->withSession(profileSession($merchant))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'New Shop',
            'email' => 'business@example.test',
            'phone' => '77416103',
            'merchant_id' => 999999,
            'user_id' => $owner->id,
            'status' => 0,
        ])
        ->assertSessionHasErrors(['merchant_id', 'user_id', 'status']);

    $this->actingAs($owner)
        ->withSession(profileSession($merchant))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'New Shop',
            'email' => 'business@example.test',
            'phone' => '77416103',
        ])
        ->assertRedirect(route('merchant.business-profile.edit'));

    $merchant->refresh();
    $owner->refresh();

    expect($merchant->name)->toBe('New Shop')
        ->and($merchant->email)->toBe('business@example.test')
        ->and($merchant->phone)->toBe('77416103')
        ->and($owner->name)->toBe('Owner Person')
        ->and($owner->email)->toBe('owner-login@example.test')
        ->and($owner->phone)->toBe('999000111');
});

test('staff without update permission cannot modify business profile', function () {
    $merchant = Merchant::factory()->create(['phone' => '91112222']);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    profileMembership($owner, $merchant, Role::Owner);
    profileMembership($staff, $merchant, Role::Staff);

    $this->actingAs($staff)
        ->withSession(profileSession($merchant))
        ->get(route('merchant.business-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canUpdate', false));

    $this->actingAs($staff)
        ->withSession(profileSession($merchant))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'Hacked',
            'email' => 'hacked@example.test',
            'phone' => '78888888',
        ])
        ->assertForbidden();

    expect($merchant->fresh()->name)->not->toBe('Hacked')
        ->and($merchant->fresh()->phone)->toBe('91112222');
});

test('merchant profile update permission can be granted to a manager', function () {
    $merchant = Merchant::factory()->create(['name' => 'Grant Shop']);
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    profileMembership($owner, $merchant, Role::Owner);
    $managerMembership = profileMembership($manager, $merchant, Role::Manager);

    $this->actingAs($manager)
        ->withSession(profileSession($merchant))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'Should Fail',
            'email' => 'fail@example.test',
            'phone' => '78888888',
        ])
        ->assertForbidden();

    app(MerchantPermissionService::class)->syncPermissions($managerMembership, [
        ...array_map(fn ($key) => $key->value, PermissionKey::managerDefaults()),
        PermissionKey::MerchantProfileUpdate->value,
    ], log: false);

    $this->actingAs($manager)
        ->withSession(profileSession($merchant))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'Granted Shop',
            'email' => 'granted@example.test',
            'phone' => '+96877416103',
        ])
        ->assertRedirect();

    expect($merchant->fresh()->name)->toBe('Granted Shop')
        ->and($merchant->fresh()->phone)->toBe('+96877416103');
});

test('merchant A cannot edit merchant B', function () {
    $merchantA = Merchant::factory()->create(['name' => 'Shop A', 'phone' => '71111111']);
    $merchantB = Merchant::factory()->create(['name' => 'Shop B', 'phone' => '72222222']);
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    profileMembership($ownerA, $merchantA, Role::Owner);
    profileMembership($ownerB, $merchantB, Role::Owner);

    $this->actingAs($ownerA)
        ->withSession(profileSession($merchantB))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'Taken Over',
            'email' => 'takeover@example.test',
            'phone' => '73333333',
        ])
        ->assertRedirect();

    expect($merchantB->fresh()->name)->toBe('Shop B')
        ->and($merchantA->fresh()->name)->toBe('Shop A');

    $this->actingAs($ownerA)
        ->withSession(profileSession($merchantA))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'Shop A Updated',
            'email' => 'a@example.test',
            'phone' => '71111111',
            'merchant_id' => $merchantB->id,
        ])
        ->assertSessionHasErrors('merchant_id');

    expect($merchantB->fresh()->name)->toBe('Shop B');
});

test('updated business phone is used for customer offer whatsapp url', function () {
    App::setLocale('en');

    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create([
        'name' => 'WhatsApp Shop',
        'phone' => '90000000',
    ]);
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    $owner = User::factory()->create(['phone' => '999000111']);
    profileMembership($owner, $merchant, Role::Owner);

    $customerUser = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $customerUser->id,
        'phone' => '0100555666',
        'email' => $customerUser->email,
    ]);
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($request);
    MerchantOffer::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchant->id,
        'price' => '12.500',
        'status' => OfferStatus::Submitted,
        'availability_status' => AvailabilityStatus::Available,
        'submitted_at' => now(),
    ]);

    $this->actingAs($owner)
        ->withSession(profileSession($merchant))
        ->patch(route('merchant.business-profile.update'), [
            'name' => 'WhatsApp Shop',
            'email' => 'shop@example.test',
            'phone' => '77416103',
        ])
        ->assertRedirect();

    expect($merchant->fresh()->phone)->toBe('77416103')
        ->and($owner->fresh()->phone)->toBe('999000111');

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.whatsapp_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96877416103?text='))
            ->missing('offers.0.merchant')
            ->missing('request.customer.phone')
        );
});
