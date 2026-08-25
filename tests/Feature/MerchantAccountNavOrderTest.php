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

test('merchant tools are composed inside merchant account after businesses and before create another business', function () {
    $accountNav = file_get_contents(resource_path('js/Composables/useAccountNav.js'));
    $dashboardNav = file_get_contents(resource_path('js/Composables/Dashboard/useDashboardNav.js'));
    $customerNav = file_get_contents(resource_path('js/Composables/Customer/useCustomerNav.js'));
    $dashboardLayout = file_get_contents(resource_path('js/Layouts/DashboardLayout.vue'));
    $customerLayout = file_get_contents(resource_path('js/Layouts/CustomerLayout.vue'));
    $accountNavMenu = file_get_contents(resource_path('js/Components/Account/AccountNavMenu.vue'));
    $sideMenu = file_get_contents(resource_path('js/Components/Layout/Dashboard/SideMenu.vue'));

    $toolPush = strpos($accountNav, 'merchantChildren.push(...merchantToolItems.value)');
    $startPush = strpos($accountNav, "id: 'merchant-start'");
    $customerSection = strpos($accountNav, "id: 'customer-account'");
    $merchantSection = strpos($accountNav, "id: 'merchant-account'");
    $marketerSection = strpos($accountNav, "id: 'marketer-account'");
    $merchantHome = strpos($accountNav, "id: 'merchant-home'");
    $merchantRequests = strpos($accountNav, "id: 'merchant-requests'");
    $merchantActivities = strpos($accountNav, "id: 'merchant-activities'");
    $merchantTeam = strpos($accountNav, "id: 'merchant-team'");
    $merchantProfile = strpos($accountNav, "id: 'merchant-business-profile'");
    $selectMerchant = strpos($accountNav, 'selectMerchant(merchant.public_id)');

    expect($toolPush)->toBeGreaterThan(0)
        ->and($startPush)->toBeGreaterThan($toolPush)
        ->and($merchantSection)->toBeGreaterThan($customerSection)
        ->and($marketerSection)->toBeGreaterThan($merchantSection)
        ->and($marketerSection)->toBeGreaterThan($startPush)
        ->and($merchantHome)->toBeGreaterThan(0)
        ->and($merchantRequests)->toBeGreaterThan($merchantHome)
        ->and($merchantActivities)->toBeGreaterThan($merchantRequests)
        ->and($merchantTeam)->toBeGreaterThan($merchantActivities)
        ->and($merchantProfile)->toBeGreaterThan($merchantTeam)
        ->and($startPush)->toBeGreaterThan($merchantProfile)
        ->and(substr_count($accountNav, "id: 'merchant-home'"))->toBe(1)
        ->and(substr_count($accountNav, 'merchantChildren.push(...merchantToolItems.value)'))->toBe(1)
        ->and($selectMerchant)->toBeGreaterThan(0)
        ->and($selectMerchant)->toBeLessThan($toolPush)
        ->and($accountNav)->not->toContain('merchant-${merchant.public_id}-home')
        ->and($accountNav)->not->toContain('merchantToolItems,')
        ->and($dashboardNav)->not->toContain('merchantToolItems')
        ->and($dashboardNav)->toContain('...accountSections.value')
        ->and($customerNav)->not->toContain('merchantToolItems')
        ->and($customerNav)->toContain('...accountSections.value')
        ->and($accountNavMenu)->toContain('accountSections')
        ->and($accountNavMenu)->not->toContain('merchantToolItems')
        ->and(substr_count($dashboardLayout, '<SideMenu :items="menuItems"'))->toBe(1)
        ->and(substr_count($customerLayout, '<SideMenu :items="menuItems"'))->toBe(1)
        ->and($sideMenu)->toContain('md:hidden')
        ->and($sideMenu)->toContain('md:translate-x-0');
});

test('triple non-admin user still uses unified account nav while admin does not', function () {
    $triple = User::factory()->create();
    Customer::factory()->create([
        'user_id' => $triple->id,
        'status' => CustomerStatus::Active,
    ]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $triple->id,
        'merchant_id' => Merchant::factory(),
        'status' => MembershipStatus::Active,
    ]);
    Marketer::factory()->create(['user_id' => $triple->id]);

    $this->actingAs($triple)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', false)
            ->where('auth.showUnifiedAccountNav', true)
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMerchantMemberships', true)
            ->where('auth.capabilities.hasActiveMarketer', true));

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Customer::factory()->create([
        'user_id' => $admin->id,
        'status' => CustomerStatus::Active,
    ]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $admin->id,
        'merchant_id' => Merchant::factory(),
        'status' => MembershipStatus::Active,
    ]);
    Marketer::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', true)
            ->where('auth.showUnifiedAccountNav', false));
});

test('switching merchant context stays a single workspace post and does not invent extra memberships', function () {
    $user = User::factory()->create();
    $first = Merchant::factory()->create(['name' => 'First Shop']);
    $second = Merchant::factory()->create(['name' => 'Second Shop']);
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $first->id,
        'status' => MembershipStatus::Active,
    ]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $second->id,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $first->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('merchantContext.public_id', $first->public_id)
            ->has('availableMerchants', 2));

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $second->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('merchantContext.public_id', $second->public_id)
            ->has('availableMerchants', 2));
});
