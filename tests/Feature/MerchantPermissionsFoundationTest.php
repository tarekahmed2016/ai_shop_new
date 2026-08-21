<?php

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantPermission;
use App\Models\MerchantUser;
use App\Models\MerchantUserPermission;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Support\MerchantContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    app(MerchantPermissionService::class)->seedCatalog();
});

function permMembership(User $user, Merchant $merchant, Role $role = Role::Staff, MembershipStatus $status = MembershipStatus::Active): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

function permSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function establishContext(User $user, Merchant $merchant): void
{
    $membership = MerchantUser::query()
        ->where('user_id', $user->id)
        ->where('merchant_id', $merchant->id)
        ->where('status', MembershipStatus::Active)
        ->firstOrFail();

    app(MerchantContext::class)->set($merchant, $membership);
}

test('permission in merchant A does not apply in merchant B', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $user = User::factory()->create();
    $membershipA = permMembership($user, $merchantA, Role::Staff);
    $membershipB = permMembership($user, $merchantB, Role::Staff);

    app(MerchantPermissionService::class)->syncPermissions(
        $membershipA,
        [PermissionKey::ActivitiesManage->value, PermissionKey::ActivitiesView->value, PermissionKey::TeamView->value, PermissionKey::RequestsView->value, PermissionKey::RequestsViewDetails->value, PermissionKey::MerchantProfileView->value],
        log: false,
    );

    app(MerchantPermissionService::class)->syncPermissions(
        $membershipB,
        PermissionKey::staffDefaults() === [] ? [] : array_map(fn ($k) => $k->value, PermissionKey::staffDefaults()),
        log: false,
    );

    establishContext($user, $merchantA);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::ActivitiesManage->value))->toBeTrue();

    establishContext($user, $merchantB);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::ActivitiesManage->value))->toBeFalse();
});

test('forged merchant context and inactive states cannot use permissions', function () {
    $merchant = Merchant::factory()->create();
    $other = Merchant::factory()->create();
    $user = User::factory()->create();
    permMembership($user, $merchant, Role::Manager);

    establishContext($user, $merchant);
    expect(app(MerchantPermissionService::class)->can($user, $other, PermissionKey::TeamView->value))->toBeFalse();

    $inactiveMembershipUser = User::factory()->create();
    permMembership($inactiveMembershipUser, $merchant, Role::Manager, MembershipStatus::Inactive);
    expect(app(MerchantPermissionService::class)->can(
        $inactiveMembershipUser,
        $merchant,
        PermissionKey::TeamView->value
    ))->toBeFalse();

    $inactiveMerchant = Merchant::factory()->create(['status' => MerchantStatus::Inactive]);
    $activeUser = User::factory()->create();
    permMembership($activeUser, $inactiveMerchant, Role::Owner);
    expect(app(MerchantPermissionService::class)->can(
        $activeUser,
        $inactiveMerchant,
        PermissionKey::TeamView->value
    ))->toBeFalse();
});

test('owner has full merchant access and can customize manager and staff permissions', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $staff = User::factory()->create();
    permMembership($owner, $merchant, Role::Owner);
    $managerMembership = permMembership($manager, $merchant, Role::Manager);
    $staffMembership = permMembership($staff, $merchant, Role::Staff);

    $this->actingAs($owner)->withSession(permSession($merchant));
    establishContext($owner, $merchant);

    foreach (PermissionKey::cases() as $key) {
        expect(app(MerchantPermissionService::class)->currentCan($key->value))->toBeTrue();
    }

    $customManager = [
        PermissionKey::RequestsView->value,
        PermissionKey::TeamView->value,
        PermissionKey::MerchantProfileView->value,
    ];

    $this->actingAs($owner)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $managerMembership), [
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => $customManager,
        ])
        ->assertRedirect();

    expect($managerMembership->fresh()->permissions()->pluck('key')->sort()->values()->all())
        ->toEqual(collect($customManager)->sort()->values()->all());

    $customStaff = [
        PermissionKey::RequestsView->value,
        PermissionKey::ActivitiesView->value,
        PermissionKey::TeamView->value,
        PermissionKey::MerchantProfileView->value,
    ];

    $this->actingAs($owner)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $staffMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => $customStaff,
        ])
        ->assertRedirect();

    expect($staffMembership->fresh()->permissions()->pluck('key')->all())->toEqualCanonicalizing($customStaff)
        ->and(ActivityLog::query()->whereJsonContains('metadata->action', 'merchant.member.permissions_updated')->exists())->toBeTrue();
});

test('owner cannot grant platform admin and owner protections cannot be removed via permissions', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $ownerMembership = permMembership($owner, $merchant, Role::Owner);
    $staffMembership = permMembership($staff, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $staffMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => ['admin', 'platform.admin', PermissionKey::TeamView->value],
        ])
        ->assertSessionHasErrors('permissions');

    expect($staff->fresh()->hasRole('admin'))->toBeFalse();

    app(MerchantPermissionService::class)->syncPermissions($ownerMembership, [], actor: $ownerMembership, log: false);

    establishContext($owner, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::TeamManagePermissions->value))->toBeTrue()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::ActivitiesManage->value))->toBeTrue();
});

test('manager defaults work and manager cannot escalate or edit managers or owners', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $otherManager = User::factory()->create();
    $staff = User::factory()->create();
    $ownerMembership = permMembership($owner, $merchant, Role::Owner);
    $managerMembership = permMembership($manager, $merchant, Role::Manager);
    $otherManagerMembership = permMembership($otherManager, $merchant, Role::Manager);
    $staffMembership = permMembership($staff, $merchant, Role::Staff);

    establishContext($manager, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::RequestsDismiss->value))->toBeTrue()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::TeamAddManager->value))->toBeFalse()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::TeamManagePermissions->value))->toBeFalse();

    $this->actingAs($manager)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $staffMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => [
                PermissionKey::TeamAddManager->value,
                PermissionKey::TeamManagePermissions->value,
            ],
        ])
        ->assertSessionHasErrors('permissions');

    $this->actingAs($manager)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $otherManagerMembership), [
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => [PermissionKey::TeamView->value],
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $ownerMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Inactive->value,
        ])
        ->assertForbidden();
});

test('staff defaults and permission gates for activities dismiss and team', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    permMembership($owner, $merchant, Role::Owner);
    $staffMembership = permMembership($staff, $merchant, Role::Staff);
    $category = Category::factory()->create();

    establishContext($staff, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::RequestsView->value))->toBeTrue()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::ActivitiesManage->value))->toBeFalse()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::RequestsDismiss->value))->toBeFalse()
        ->and(app(MerchantPermissionService::class)->currentCan(PermissionKey::TeamManagePermissions->value))->toBeFalse();

    $this->actingAs($staff)
        ->withSession(permSession($merchant))
        ->get(route('merchant.activities.index'))
        ->assertOk();

    $this->actingAs($staff)
        ->withSession(permSession($merchant))
        ->post(route('merchant.activities.store'), ['category_id' => $category->public_id])
        ->assertForbidden();

    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchant->id,
        'status' => MatchStatus::Pending,
    ]);

    $this->actingAs($staff)
        ->withSession(permSession($merchant))
        ->post(route('merchant.requests.dismiss', $request))
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession(permSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'nope@example.com',
            'name' => 'Nope',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();

    app(MerchantPermissionService::class)->syncPermissions($staffMembership, [
        PermissionKey::RequestsView->value,
        PermissionKey::RequestsViewDetails->value,
        PermissionKey::RequestsDismiss->value,
        PermissionKey::ActivitiesView->value,
        PermissionKey::TeamView->value,
        PermissionKey::MerchantProfileView->value,
    ], log: false);

    $this->actingAs($staff)
        ->withSession(permSession($merchant))
        ->post(route('merchant.requests.dismiss', $request))
        ->assertRedirect(route('merchant.requests.index'));
});

test('team edit form loads grouped permissions and selected permissions persist', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    permMembership($owner, $merchant, Role::Owner);
    $staffMembership = permMembership($staff, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(permSession($merchant))
        ->get(route('merchant.team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantTeamPage', false)
            ->has('permissionCatalog')
            ->has('assignablePermissions.merchant-staff')
            ->where('canCustomizePermissions', true)
        );

    $permissions = [
        PermissionKey::RequestsView->value,
        PermissionKey::TeamView->value,
        PermissionKey::MerchantProfileView->value,
    ];

    $this->actingAs($owner)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $staffMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => $permissions,
        ])
        ->assertRedirect();

    expect($staffMembership->fresh()->permissions()->pluck('key')->all())->toEqualCanonicalizing($permissions);

    establishContext($staff, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::ActivitiesView->value))->toBeFalse();

    $this->actingAs($owner)
        ->withSession(permSession($merchant))
        ->patch(route('merchant.team.update', $staffMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'permissions' => array_merge($permissions, [PermissionKey::ActivitiesView->value]),
        ])
        ->assertRedirect();

    establishContext($staff, $merchant);
    expect(app(MerchantPermissionService::class)->currentCan(PermissionKey::ActivitiesView->value))->toBeTrue();
});

test('existing memberships receive role defaults and reseeding does not duplicate', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $staff = User::factory()->create();

    // Simulate pre-permission memberships by inserting without factory hooks.
    $ownerId = DB::table('merchant_user')->insertGetId([
        'merchant_id' => $merchant->id,
        'user_id' => $owner->id,
        'role' => Role::Owner->value,
        'status' => MembershipStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $managerId = DB::table('merchant_user')->insertGetId([
        'merchant_id' => $merchant->id,
        'user_id' => $manager->id,
        'role' => Role::Manager->value,
        'status' => MembershipStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $staffId = DB::table('merchant_user')->insertGetId([
        'merchant_id' => $merchant->id,
        'user_id' => $staff->id,
        'role' => Role::Staff->value,
        'status' => MembershipStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(MerchantPermissionService::class);
    $service->seedCatalog();
    $updated = $service->backfillMissingDefaults();

    expect($updated)->toBe(3)
        ->and(MerchantUser::find($ownerId)->permissions()->count())->toBe(count(PermissionKey::ownerDefaults()))
        ->and(MerchantUser::find($managerId)->permissions()->pluck('key')->all())
        ->toEqualCanonicalizing(array_map(fn ($k) => $k->value, PermissionKey::managerDefaults()))
        ->and(MerchantUser::find($staffId)->permissions()->pluck('key')->all())
        ->toEqualCanonicalizing(array_map(fn ($k) => $k->value, PermissionKey::staffDefaults()));

    $permissionCount = MerchantPermission::query()->count();
    $assignmentCount = MerchantUserPermission::query()->count();

    $service->seedCatalog();
    $second = $service->backfillMissingDefaults();

    expect($second)->toBe(0)
        ->and(MerchantPermission::query()->count())->toBe($permissionCount)
        ->and(MerchantUserPermission::query()->count())->toBe($assignmentCount);
});

test('platform admin membership management still works without merchant permissions', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.memberships.store', $merchant), [
            'user_id' => $user->id,
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    $membership = MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $user->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership->permissions()->count())->toBe(count(PermissionKey::staffDefaults()));

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk();
});
