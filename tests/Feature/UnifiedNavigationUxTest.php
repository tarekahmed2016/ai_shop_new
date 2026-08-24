<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function navigationOwner(): array
{
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    return compact('user', 'merchant');
}

test('dual user sees customer capability and merchant list on both customer and merchant pages', function () {
    ['user' => $user, 'merchant' => $merchant] = navigationOwner();
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('availableMerchants.0.public_id', $merchant->public_id)
            ->missing('availableMerchants.0.id'));

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('availableMerchants.0.public_id', $merchant->public_id)
            ->where('availableMerchants.0.current', true)
            ->where('merchantContext.public_id', $merchant->public_id));
});

test('customer-only user sees start-selling path without merchant memberships', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', false)
            ->has('availableMerchants', 0));

    $this->actingAs($user)
        ->get(route('account.merchant.start'))
        ->assertOk();
});

test('merchant-only user still has customer enable path', function () {
    ['user' => $user, 'merchant' => $merchant] = navigationOwner();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id]);

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.hasCustomer', false)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('availableMerchants.0.public_id', $merchant->public_id));

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertRedirect(route('account.customer.enable'));
});

test('user without capabilities can open customer enable and start selling', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertRedirect(route('account.customer.enable'));

    $this->actingAs($user)
        ->get(route('account.customer.enable'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('account.merchant.start'))
        ->assertOk();
});

test('multiple businesses remain selectable by public_id only', function () {
    $user = User::factory()->create();
    $merchantA = Merchant::factory()->create(['name' => 'Biz A']);
    $merchantB = Merchant::factory()->create(['name' => 'Biz B']);
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantA->id,
        'status' => MembershipStatus::Active,
    ]);
    MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantB->id,
        'role' => Role::Manager,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('account.merchant.start'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableMerchants', 2)
            ->missing('availableMerchants.0.id')
            ->missing('availableMerchants.1.merchant_id'));

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantB->public_id])
        ->assertRedirect(route('merchant.home'));

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantB->id);
});

test('public pages share capability-aware auth.home for return to dashboard', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.home', null));

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('dashboard', absolute: false)));

    $customer = User::factory()->create();
    Customer::factory()->create([
        'user_id' => $customer->id,
        'status' => CustomerStatus::Active,
    ]);
    $this->actingAs($customer)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('customer.home', absolute: false)));

    ['user' => $merchantUser] = navigationOwner();
    $this->actingAs($merchantUser)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('dashboard', absolute: false)));

    $dual = $merchantUser;
    Customer::factory()->create([
        'user_id' => $dual->id,
        'status' => CustomerStatus::Active,
    ]);
    $this->actingAs($dual)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('dashboard', absolute: false)));

    $plain = User::factory()->create();
    $this->actingAs($plain)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('account.get-started', absolute: false)));
});
