<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function adminNavUser(array $capabilities = []): User
{
    $user = User::factory()->create();

    if ($capabilities['admin'] ?? false) {
        $user->assignRole('admin');
    }

    if ($capabilities['customer'] ?? false) {
        Customer::factory()->create([
            'user_id' => $user->id,
            'status' => CustomerStatus::Active,
        ]);
    }

    if ($capabilities['merchant'] ?? false) {
        MerchantUser::factory()->owner()->create([
            'user_id' => $user->id,
            'merchant_id' => Merchant::factory(),
            'status' => MembershipStatus::Active,
        ]);
    }

    if ($capabilities['marketer'] ?? false) {
        Marketer::factory()->create(['user_id' => $user->id]);
    }

    return $user->fresh();
}

test('admin dashboard hides unified account sections even with customer merchant and marketer capabilities', function () {
    $admin = adminNavUser([
        'admin' => true,
        'customer' => true,
        'merchant' => true,
        'marketer' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage', false)
            ->where('auth.isAdmin', true)
            ->where('auth.showUnifiedAccountNav', false)
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('auth.capabilities.hasActiveMarketer', true)
            ->where('auth.capabilities.hasCustomer', true)
            ->where('auth.capabilities.hasMerchantMemberships', true)
            ->where('auth.capabilities.hasMarketer', true));
});

test('admin still sees admin merchants customers and marketers management pages', function () {
    $admin = adminNavUser(['admin' => true]);

    $this->actingAs($admin)->get(route('merchants.index'))->assertOk();
    $this->actingAs($admin)->get(route('customers.index'))->assertOk();
    $this->actingAs($admin)->get(route('marketers.index'))->assertOk();
});

test('normal customer still shows unified account navigation', function () {
    $customer = adminNavUser(['customer' => true]);

    $this->actingAs($customer)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', false)
            ->where('auth.showUnifiedAccountNav', true)
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', false)
            ->where('auth.capabilities.hasActiveMarketer', false));
});

test('normal merchant still shows unified account navigation on the dashboard sidebar', function () {
    $merchantUser = adminNavUser(['merchant' => true]);

    $this->actingAs($merchantUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', false)
            ->where('auth.showUnifiedAccountNav', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('auth.capabilities.hasActiveCustomer', false)
            ->where('auth.capabilities.hasActiveMarketer', false));
});

test('active marketer still shows unified account navigation', function () {
    $marketerUser = adminNavUser(['marketer' => true]);

    $this->actingAs($marketerUser)
        ->get(route('marketer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', false)
            ->where('auth.showUnifiedAccountNav', true)
            ->where('auth.capabilities.hasActiveMarketer', true)
            ->where('auth.capabilities.hasActiveCustomer', false)
            ->where('auth.capabilities.hasActiveMerchantMemberships', false));
});

test('dual non-admin user still shows unified account navigation for applicable capabilities', function () {
    $dual = adminNavUser([
        'customer' => true,
        'merchant' => true,
    ]);

    $this->actingAs($dual)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', false)
            ->where('auth.showUnifiedAccountNav', true)
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('auth.capabilities.hasActiveMarketer', false));
});

test('triple non-admin user still shows unified account navigation for all capabilities', function () {
    $triple = adminNavUser([
        'customer' => true,
        'merchant' => true,
        'marketer' => true,
    ]);

    $this->actingAs($triple)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', false)
            ->where('auth.showUnifiedAccountNav', true)
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('auth.capabilities.hasActiveMarketer', true));
});

test('desktop sidebar and mobile drawer use the same dashboard nav source', function () {
    $layout = file_get_contents(resource_path('js/Layouts/DashboardLayout.vue'));
    $nav = file_get_contents(resource_path('js/Composables/Dashboard/useDashboardNav.js'));
    $sideMenu = file_get_contents(resource_path('js/Components/Layout/Dashboard/SideMenu.vue'));

    expect(substr_count($layout, 'useDashboardNav()'))->toBe(1)
        ->and(substr_count($layout, '<SideMenu :items="menuItems"'))->toBe(1)
        ->and($nav)->toContain('showUnifiedAccountNav')
        ->and($nav)->toContain('...accountSections.value')
        ->and($nav)->not->toContain('...merchantToolItems.value')
        ->and($sideMenu)->toContain('props.collapsed')
        ->and($sideMenu)->toContain('md:hidden')
        ->and($sideMenu)->toContain('md:translate-x-0')
        ->and($sideMenu)->toContain('min-h-0')
        ->and($sideMenu)->toContain('overflow-y-auto')
        ->and(substr_count($sideMenu, 'defineProps'))->toBe(1);
});
