<?php

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role as MembershipRole;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOfferCredits\TransactionSource as CreditSource;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Payments\Method;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Marketer;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\PaymentTransaction;
use App\Models\Service;
use App\Models\User;
use App\Support\AdminPermissionCatalog;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

function categoryStorePayload(): array
{
    return [
        'name_ar' => 'إلكترونيات',
        'name_en' => 'Electronics',
        'status' => CategoryStatus::Active->value,
        'sort_order' => 1,
    ];
}

function customerStorePayload(): array
{
    return [
        'name' => 'Limited Customer',
        'phone' => '01000000999',
        'email' => 'limited-customer@example.test',
        'password' => 'password12',
        'password_confirmation' => 'password12',
        'status' => CustomerStatus::Active->value,
    ];
}

test('admin without a view permission is forbidden on direct urls', function (string $routeName) {
    $admin = adminWithPermissions([]);
    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'categories' => ['categories.index'],
    'merchants' => ['merchants.index'],
    'merchant credits' => ['merchants.credits.transactions'],
    'customers' => ['customers.index'],
    'customer requests' => ['customer-requests.index'],
    'matching' => ['matching.index'],
    'payments' => ['payments.index'],
    'marketers' => ['marketers.index'],
    'users' => ['users.index'],
    'roles' => ['roles.index'],
    'services' => ['services.index'],
    'settings' => ['company-info.index'],
    'newsletter' => ['newsletter-subscribers.index'],
    'hero slides' => ['hero-slides.index'],
    'homepage promos' => ['homepage-promos.index'],
]);

test('admin without permission is forbidden on model-bound admin urls', function () {
    $admin = adminWithPermissions([]);
    $merchant = Merchant::factory()->create();
    $customer = Customer::factory()->create();
    $payment = PaymentTransaction::factory()->create();
    $marketer = Marketer::factory()->create();

    $this->actingAs($admin)->get(route('merchants.credits.index', $merchant))->assertForbidden();
    $this->actingAs($admin)->get(route('customers.extra-requests.index', $customer))->assertForbidden();
    $this->actingAs($admin)->get(route('payments.show', $payment))->assertForbidden();
    $this->actingAs($admin)->get(route('marketers.show', $marketer))->assertForbidden();
});

test('admin with the view permission can open the page', function (string $permission, string $routeName) {
    $admin = adminWithPermissions([$permission]);

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'categories' => ['categories.view', 'categories.index'],
    'merchants' => ['merchants.view', 'merchants.index'],
    'customers' => ['customers.view', 'customers.index'],
    'customer requests' => ['customer-requests.view', 'customer-requests.index'],
    'matching' => ['matching.view', 'matching.index'],
    'payments' => ['payments.view', 'payments.index'],
    'marketers' => ['marketers.view', 'marketers.index'],
    'users' => ['users.view', 'users.index'],
    'roles' => ['roles.view', 'roles.index'],
    'services' => ['services.view', 'services.index'],
    'settings' => ['settings.update', 'company-info.index'],
    'newsletter' => ['newsletter-subscribers.view', 'newsletter-subscribers.index'],
    'hero slides' => ['hero-slides.view', 'hero-slides.index'],
    'homepage promos' => ['homepage-promos.view', 'homepage-promos.index'],
    'credit history' => ['merchant-credits.view', 'merchants.credits.transactions'],
]);

test('view-only admin cannot mutate', function (array $view, string $method, string $routeName, callable $payload) {
    [$params, $body] = $payload();
    $admin = adminWithPermissions($view);

    $this->actingAs($admin)
        ->{$method}(route($routeName, $params), $body)
        ->assertForbidden();
})->with([
    'create category' => [
        ['categories.view'],
        'post',
        'categories.store',
        fn () => [[], categoryStorePayload()],
    ],
    'create merchant' => [
        ['merchants.view'],
        'post',
        'merchants.store',
        fn () => [[], [
            'name' => 'Blocked Shop',
            'phone' => '0123456789',
            'email' => 'blocked-shop@example.test',
            'status' => MerchantStatus::Active->value,
            'category_ids' => [Category::factory()->create()->public_id],
            'owner_name' => 'Owner',
            'owner_email' => 'blocked-owner@example.test',
            'owner_phone' => '0111111111',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ]],
    ],
    'create customer' => [
        ['customers.view'],
        'post',
        'customers.store',
        fn () => [[], customerStorePayload()],
    ],
    'add extra requests' => [
        ['customers.view'],
        'post',
        'customers.extra-requests.store',
        fn () => [[Customer::factory()->create()], [
            'amount' => 2,
            'source' => ExtraSource::Cash->value,
        ]],
    ],
    'daily limit' => [
        ['customers.view'],
        'put',
        'customers.settings.daily-request-limit',
        fn () => [[], ['daily_limit' => 4]],
    ],
    'recalculate matches' => [
        ['matching.view', 'customer-requests.view'],
        'post',
        'customer-requests.match',
        fn () => [[CustomerRequest::factory()->create()], []],
    ],
    'add merchant credits' => [
        ['merchant-credits.view', 'merchants.view'],
        'post',
        'merchants.credits.store',
        fn () => [[Merchant::factory()->create()], [
            'amount' => 5,
            'source' => CreditSource::Cash->value,
        ]],
    ],
    'approve marketer' => [
        ['marketers.view'],
        'post',
        'marketers.approve',
        fn () => [[Marketer::factory()->pending()->create()], []],
    ],
    'record payout' => [
        ['marketers.view'],
        'post',
        'marketers.payouts.store',
        fn () => [[Marketer::factory()->create()], [
            'amount' => '1.000',
            'payment_method' => Method::Cash->value,
            'paid_at' => now()->toDateString(),
        ]],
    ],
    'commission settings' => [
        ['marketers.view'],
        'put',
        'marketer-commissions.settings',
        fn () => [[], [
            'customer_commission_rate' => 5,
            'merchant_commission_rate' => 5,
        ]],
    ],
    'create user' => [
        ['users.view'],
        'post',
        'users.store',
        fn () => [[], [
            'name' => 'Blocked Staff',
            'email' => 'blocked-staff@example.test',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => tap(SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']), fn () => null)->name,
        ]],
    ],
    'update settings' => [
        ['users.view'],
        'put',
        'company-info.update',
        fn () => [[], [
            'name_ar' => 'محظور',
            'name_en' => 'Blocked',
        ]],
    ],
    'delete service' => [
        ['services.view'],
        'delete',
        'services.destroy',
        fn () => [[Service::factory()->create()], []],
    ],
]);

test('admin with the specific permission can perform the action', function (array $permissions, string $method, string $routeName, callable $payload) {
    [$params, $body] = $payload();
    $admin = adminWithPermissions($permissions);

    $this->actingAs($admin)
        ->{$method}(route($routeName, $params), $body)
        ->assertRedirect();
})->with([
    'create category' => [
        ['categories.create'],
        'post',
        'categories.store',
        fn () => [[], categoryStorePayload()],
    ],
    'create customer' => [
        ['customers.create'],
        'post',
        'customers.store',
        fn () => [[], customerStorePayload()],
    ],
    'add extra requests' => [
        ['customers.manage-limits'],
        'post',
        'customers.extra-requests.store',
        fn () => [[Customer::factory()->create()], [
            'amount' => 2,
            'source' => ExtraSource::Cash->value,
        ]],
    ],
    'daily limit' => [
        ['customers.manage-limits'],
        'put',
        'customers.settings.daily-request-limit',
        fn () => [[], ['daily_limit' => 4, 'notes' => 'c4']],
    ],
    'recalculate matches' => [
        ['matching.recalculate'],
        'post',
        'customer-requests.match',
        fn () => [[CustomerRequest::factory()->create()], []],
    ],
    'approve marketer' => [
        ['marketers.approve'],
        'post',
        'marketers.approve',
        fn () => [[Marketer::factory()->pending()->create()], []],
    ],
    'commission settings' => [
        ['marketer-commissions.manage-settings'],
        'put',
        'marketer-commissions.settings',
        fn () => [[], [
            'customer_commission_rate' => 5,
            'merchant_commission_rate' => 5,
        ]],
    ],
    'create user' => [
        ['users.create'],
        'post',
        'users.store',
        fn () => [[], [
            'name' => 'Allowed Staff',
            'email' => 'allowed-staff@example.test',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web'])->name,
        ]],
    ],
]);

test('full admin role still reaches marketplace and cms modules', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $payment = PaymentTransaction::factory()->create();
    $marketer = Marketer::factory()->create();

    $this->actingAs($admin)->get(route('categories.index'))->assertOk();
    $this->actingAs($admin)->get(route('merchants.index'))->assertOk();
    $this->actingAs($admin)->get(route('customers.index'))->assertOk();
    $this->actingAs($admin)->get(route('customer-requests.index'))->assertOk();
    $this->actingAs($admin)->get(route('matching.index'))->assertOk();
    $this->actingAs($admin)->get(route('payments.show', $payment))->assertOk();
    $this->actingAs($admin)->get(route('marketers.show', $marketer))->assertOk();
    $this->actingAs($admin)->get(route('users.index'))->assertOk();
    $this->actingAs($admin)->get(route('roles.index'))->assertOk();
    $this->actingAs($admin)->get(route('company-info.index'))->assertOk();
    $this->actingAs($admin)->get(route('hero-slides.index'))->assertOk();
    $this->actingAs($admin)->get(route('newsletter-subscribers.index'))->assertOk();
});

test('seeded admin role receives the full permission catalog', function () {
    $role = SpatieRole::findByName('admin', 'web');

    foreach (AdminPermissionCatalog::names() as $name) {
        expect($role->hasPermissionTo($name))->toBeTrue();
    }
});

test('last administrator cannot be deleted or demoted after permission enforcement', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertSessionHasErrors('user');

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('admin role management still creates a custom role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $permissionId = $admin->getAllPermissions()->firstWhere('name', 'users.view')?->id;

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'content-editor',
            'permissions' => [$permissionId],
        ])
        ->assertRedirect();

    $role = SpatieRole::where('name', 'content-editor')->first();
    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('users.view'))->toBeTrue();
});

test('spatie permission without the admin role cannot enter admin routes', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('users.view');

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertRedirect(route('login'));
});

test('shared auth permissions expose the catalog and view-only is not treated as mutate', function () {
    $admin = adminWithPermissions(['users.view']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', true)
            ->where('auth.permissions', ['users.view']));

    $this->actingAs($admin)
        ->get(route('merchants.index'))
        ->assertForbidden();
});

test('membership and category ids cannot target another merchant', function () {
    $admin = adminWithPermissions(['merchants.view', 'merchants.update']);
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $membership = MerchantUser::factory()->create([
        'merchant_id' => $merchantA->id,
        'role' => MembershipRole::Staff,
        'status' => MembershipStatus::Active,
    ]);
    $assignment = MerchantCategory::factory()->create([
        'merchant_id' => $merchantA->id,
    ]);

    $this->actingAs($admin)
        ->put(route('merchants.memberships.update', [$merchantB, $membership]), [
            'role' => MembershipRole::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('merchants.categories.destroy', [$merchantB, $assignment]))
        ->assertNotFound();
});

test('admin navigation source gates modules by permission rather than isAdmin alone', function () {
    $nav = file_get_contents(resource_path('js/Composables/Dashboard/useDashboardNav.js'));

    expect($nav)->toContain('function hasPermission')
        ->and($nav)->toContain("permission: 'categories.view'")
        ->and($nav)->toContain("permission: 'merchant-credits.view'")
        ->and($nav)->toContain("permission: 'matching.view'")
        ->and($nav)->toContain("permission: 'payments.view'")
        ->and($nav)->toContain("permission: 'settings.update'");
});
