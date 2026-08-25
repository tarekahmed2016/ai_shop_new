<?php

use App\Models\Marketer;
use App\Models\PaymentTransaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('payments pages are assigned the canonical dashboard layout', function () {
    $app = file_get_contents(resource_path('js/app.js'));

    expect($app)->toContain("name.startsWith('Payments/')")
        ->and($app)->toContain("name.startsWith('Marketers/')")
        ->and($app)->toContain("name.startsWith('Merchants/')")
        ->and($app)->toContain("name.startsWith('Customers/')")
        ->and($app)->toContain('page.default.layout = DashboardLayout');
});

test('admin navigation source includes payments commissions credit history and core modules', function () {
    $nav = file_get_contents(resource_path('js/Composables/Dashboard/useDashboardNav.js'));
    $layout = file_get_contents(resource_path('js/Layouts/DashboardLayout.vue'));
    $sideMenu = file_get_contents(resource_path('js/Components/Layout/Dashboard/SideMenu.vue'));

    expect($nav)->toContain("routeName: 'payments.index'")
        ->and($nav)->toContain("routeName: 'marketer-commissions.index'")
        ->and($nav)->toContain("routeName: 'merchants.credits.transactions'")
        ->and($nav)->toContain("routeName: 'merchants.index'")
        ->and($nav)->toContain("routeName: 'customers.index'")
        ->and($nav)->toContain("routeName: 'marketers.index'")
        ->and($nav)->toContain("routeName: 'customer-requests.index'")
        ->and($nav)->toContain("routeName: 'categories.index'")
        ->and($nav)->toContain("namedRoute('users.index')")
        ->and($nav)->toContain("namedRoute('roles.index')")
        ->and($nav)->toContain("activePatterns: ['payments.index', 'payments.show']")
        ->and($nav)->toContain("'marketers.show'")
        ->and($nav)->toContain("'marketers.commissions'")
        ->and($nav)->toContain("'marketers.payouts'")
        ->and($nav)->toContain('showUnifiedAccountNav')
        ->and($nav)->not->toContain("id: 'customer-account'")
        ->and(substr_count($layout, 'useDashboardNav()'))->toBe(1)
        ->and(substr_count($layout, '<SideMenu :items="menuItems"'))->toBe(1)
        ->and($sideMenu)->toContain('min-h-0')
        ->and($sideMenu)->toContain('overflow-y-auto')
        ->and($sideMenu)->toContain('overflow-hidden')
        ->and($sideMenu)->toContain('shrink-0')
        ->and($sideMenu)->toContain('activePatterns')
        ->and($sideMenu)->toContain('md:hidden')
        ->and($sideMenu)->toContain('md:translate-x-0');
});

test('admin payments and marketer commission pages load for admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $payment = PaymentTransaction::factory()->create();
    $marketer = Marketer::factory()->create();

    $this->actingAs($admin)->get(route('payments.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/PaymentsPage', false)
            ->where('auth.isAdmin', true)
            ->where('auth.showUnifiedAccountNav', false));

    $this->actingAs($admin)->get(route('payments.show', $payment))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/PaymentShowPage', false)
            ->where('auth.showUnifiedAccountNav', false));

    $this->actingAs($admin)->get(route('marketer-commissions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketers/MarketerCommissionsIndexPage', false)
            ->where('auth.showUnifiedAccountNav', false));

    $this->actingAs($admin)->get(route('marketers.show', $marketer))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketers/MarketerShowPage', false)
            ->where('auth.showUnifiedAccountNav', false));

    $this->actingAs(creditAdmin())->get(route('merchants.credits.transactions'))->assertOk();
    $this->actingAs($admin)->get(route('merchants.index'))->assertOk();
    $this->actingAs($admin)->get(route('customers.index'))->assertOk();
    $this->actingAs($admin)->get(route('marketers.index'))->assertOk();
    $this->actingAs($admin)->get(route('customer-requests.index'))->assertOk();
    $this->actingAs($admin)->get(route('categories.index'))->assertOk();
});
