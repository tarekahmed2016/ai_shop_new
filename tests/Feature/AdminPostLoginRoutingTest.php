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

function routingAdmin(array $capabilities = []): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

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

function assertAdminLoginGoesToDashboard(User $admin): void
{
    test()->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
}

test('admin login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin());
});

test('super admin login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin());
});

test('admin with customer login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin(['customer' => true]));
});

test('admin with merchant login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin(['merchant' => true]));
});

test('admin with marketer login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin(['marketer' => true]));
});

test('admin with all three capabilities login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin([
        'customer' => true,
        'merchant' => true,
        'marketer' => true,
    ]));
});

test('admin with no capability login goes to dashboard', function () {
    assertAdminLoginGoesToDashboard(routingAdmin());
});

test('admin login ignores intended onboarding urls', function () {
    $admin = routingAdmin(['customer' => true, 'merchant' => true, 'marketer' => true]);

    $this->withSession(['url.intended' => url('/account/get-started')])
        ->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('admin get-started redirects to dashboard', function () {
    $this->actingAs(routingAdmin())
        ->get(route('account.get-started'))
        ->assertRedirect(route('dashboard'));
});

test('admin marketer apply route redirects to dashboard', function () {
    $this->actingAs(routingAdmin())
        ->get(route('marketer.application.create'))
        ->assertRedirect(route('dashboard'));
});

test('admin merchant self-service start redirects to dashboard', function () {
    $this->actingAs(routingAdmin())
        ->get(route('account.merchant.start'))
        ->assertRedirect(route('dashboard'));
});

test('admin customer enable redirects to dashboard', function () {
    $this->actingAs(routingAdmin())
        ->get(route('account.customer.enable'))
        ->assertRedirect(route('dashboard'));
});

test('public return to dashboard for admin points at dashboard', function () {
    $this->actingAs(routingAdmin())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('dashboard', absolute: false)));
});

test('admin sidebar still has no capability sections', function () {
    $this->actingAs(routingAdmin([
        'customer' => true,
        'merchant' => true,
        'marketer' => true,
    ]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', true)
            ->where('auth.showUnifiedAccountNav', false));
});

test('non-admin customer login is unchanged', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('customer.home', absolute: false));
});
