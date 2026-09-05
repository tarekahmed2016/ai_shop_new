<?php

use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOfferCredits\AdminPermission;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantOfferCreditService;
use App\Support\AdminPermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Benchmark');

pest()->beforeEach(function () {
    seedAdminPermissionCatalog();
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function seedAdminPermissionCatalog(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (AdminPermissionCatalog::names() as $name) {
        Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    $admin = SpatieRole::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);
    $admin->syncPermissions(AdminPermissionCatalog::names());

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function syncAdminRolePermissions(array $permissions): void
{
    $admin = SpatieRole::findByName('admin', 'web');
    $admin->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function adminWithPermissions(array $permissions): User
{
    seedAdminPermissionCatalog();
    syncAdminRolePermissions($permissions);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin->fresh();
}

function creditAdmin(?array $permissions = null): User
{
    seedAdminPermissionCatalog();

    if ($permissions !== null) {
        $kept = array_values(array_diff(
            AdminPermissionCatalog::names(),
            AdminPermission::values(),
        ));
        syncAdminRolePermissions(array_merge($kept, $permissions));
    }

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin->fresh();
}

function enableOfferCreditEnforcement(): void
{
    app(MerchantOfferCreditService::class)->setEnforcementEnabled(true);
}

function attachMerchantOwner(Merchant $merchant, ?User $owner = null): User
{
    $owner ??= User::factory()->create();

    MerchantUser::factory()->owner()->create([
        'user_id' => $owner->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    return $owner;
}

function enableAsyncClassification(): void
{
    config(['classification.async_enabled' => true]);
}

function revealCustomerOfferContact(User $user, MerchantOffer $offer): TestResponse
{
    $offer->loadMissing('customerRequest');

    return test()->actingAs($user)
        ->from(route('customer.requests.show', $offer->customerRequest))
        ->post(route('customer.offers.contact-reveal', $offer));
}
