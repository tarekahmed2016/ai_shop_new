<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function teamMembership(User $user, Merchant $merchant, Role $role = Role::Staff, MembershipStatus $status = MembershipStatus::Active): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

function teamSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function makeMerchantWithOwner(Role $ownerRole = Role::Owner): array
{
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $owner = User::factory()->create();
    $membership = teamMembership($owner, $merchant, $ownerRole);

    return compact('merchant', 'owner', 'membership');
}

test('owner can view current merchant team members', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();
    $staff = User::factory()->create(['name' => 'Team Staff']);
    teamMembership($staff, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->get(route('merchant.team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantTeamPage', false)
            ->has('memberships.data', 2)
            ->where('canManageTeam', true)
        );
});

test('owner can create a new staff user and membership with hashed password and no admin role', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'new-staff@example.com',
            'name' => 'New Staff',
            'phone' => '0100000001',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'new-staff@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('password12', $user->password))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and($user->status)->toBe(UserStatus::Active)
        ->and(MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $user->id)->where('role', Role::Staff)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', Event::Created)->where('subject_type', MerchantUser::class)->whereJsonContains('metadata->action', 'merchant.member.added')->exists())->toBeTrue();
});

test('owner can create a new manager', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'new-manager@example.com',
            'name' => 'New Manager',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    expect(MerchantUser::query()
        ->where('merchant_id', $merchant->id)
        ->whereHas('user', fn ($q) => $q->where('email', 'new-manager@example.com'))
        ->where('role', Role::Manager)
        ->exists())->toBeTrue();
});

test('owner can attach an existing centralized user without overwriting password', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();
    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => 'original-pass-99',
    ]);
    $hashBefore = $existing->password;

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'existing@example.com',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'password' => 'should-not-apply',
            'password_confirmation' => 'should-not-apply',
        ])
        ->assertRedirect();

    expect($existing->fresh()->password)->toBe($hashBefore)
        ->and(Hash::check('original-pass-99', $existing->fresh()->password))->toBeTrue()
        ->and(MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $existing->id)->exists())->toBeTrue();
});

test('same user can belong to multiple merchants via team attach', function () {
    ['merchant' => $merchantA, 'owner' => $ownerA] = makeMerchantWithOwner();
    ['merchant' => $merchantB] = makeMerchantWithOwner();
    $shared = User::factory()->create(['email' => 'shared@example.com']);
    teamMembership($shared, $merchantB, Role::Staff);

    $this->actingAs($ownerA)
        ->withSession(teamSession($merchantA))
        ->post(route('merchant.team.store'), [
            'email' => 'shared@example.com',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    expect($shared->merchants()->count())->toBe(2);
});

test('duplicate membership in same merchant is rejected', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();
    $staff = User::factory()->create(['email' => 'dup@example.com']);
    teamMembership($staff, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'dup@example.com',
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertSessionHasErrors('email');

    expect(MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $staff->id)->count())->toBe(1);
});

test('owner can change staff to manager and deactivate reactivate membership', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();
    $staffUser = User::factory()->create();
    $membership = teamMembership($staffUser, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $membership), [
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    expect($membership->fresh()->role)->toBe(Role::Manager);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $membership), [
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Inactive->value,
        ])
        ->assertRedirect();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Inactive);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $membership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Active)
        ->and($membership->fresh()->role)->toBe(Role::Staff);
});

test('removing membership does not delete the central user', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();
    $staffUser = User::factory()->create();
    $membership = teamMembership($staffUser, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->delete(route('merchant.team.destroy', $membership))
        ->assertRedirect();

    expect(MerchantUser::query()->find($membership->id))->toBeNull()
        ->and(User::query()->find($staffUser->id))->not->toBeNull()
        ->and(ActivityLog::query()->where('event', Event::Deleted)->whereJsonContains('metadata->action', 'merchant.member.removed')->exists())->toBeTrue();
});

test('manager can add and manage staff only', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    teamMembership($owner, $merchant, Role::Owner);
    teamMembership($manager, $merchant, Role::Manager);

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'mgr-staff@example.com',
            'name' => 'Mgr Staff',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    $staffMembership = MerchantUser::query()
        ->where('merchant_id', $merchant->id)
        ->whereHas('user', fn ($q) => $q->where('email', 'mgr-staff@example.com'))
        ->first();

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $staffMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Inactive->value,
        ])
        ->assertRedirect();

    expect($staffMembership->fresh()->status)->toBe(MembershipStatus::Inactive);
});

test('manager cannot create owner or modify owner or manage another manager', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $otherManager = User::factory()->create();
    $ownerMembership = teamMembership($owner, $merchant, Role::Owner);
    teamMembership($manager, $merchant, Role::Manager);
    $otherManagerMembership = teamMembership($otherManager, $merchant, Role::Manager);

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'fake-owner@example.com',
            'name' => 'Fake Owner',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Owner->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $ownerMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Inactive->value,
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $otherManagerMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->delete(route('merchant.team.destroy', $ownerMembership))
        ->assertForbidden();
});

test('staff cannot add edit or remove members', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $other = User::factory()->create();
    teamMembership($owner, $merchant, Role::Owner);
    teamMembership($staff, $merchant, Role::Staff);
    $otherMembership = teamMembership($other, $merchant, Role::Staff);

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->get(route('merchant.team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManageTeam', false));

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'blocked@example.com',
            'name' => 'Blocked',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $otherMembership), [
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->delete(route('merchant.team.destroy', $otherMembership))
        ->assertForbidden();
});

test('member from merchant A cannot modify merchant B team and forged membership is rejected', function () {
    ['merchant' => $merchantA, 'owner' => $ownerA] = makeMerchantWithOwner();
    ['merchant' => $merchantB, 'owner' => $ownerB] = makeMerchantWithOwner();
    $staffB = User::factory()->create();
    $membershipB = teamMembership($staffB, $merchantB, Role::Staff);

    $this->actingAs($ownerA)
        ->withSession(teamSession($merchantA))
        ->patch(route('merchant.team.update', $membershipB), [
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
            'merchant_id' => $merchantA->id,
        ])
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->withSession(teamSession($merchantA))
        ->delete(route('merchant.team.destroy', $membershipB))
        ->assertNotFound();

    expect($membershipB->fresh()->role)->toBe(Role::Staff)
        ->and(User::query()->find($ownerB->id))->not->toBeNull();
});

test('forged merchant_id in team payload is ignored and prohibited', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();
    $otherMerchant = Merchant::factory()->create();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'scoped@example.com',
            'name' => 'Scoped',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
            'merchant_id' => $otherMerchant->id,
        ])
        ->assertSessionHasErrors('merchant_id');

    expect(MerchantUser::query()->where('merchant_id', $otherMerchant->id)->count())->toBe(0);
});

test('inactive membership and inactive merchant cannot manage team', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    teamMembership($owner, $merchant, Role::Owner, MembershipStatus::Inactive);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->get(route('merchant.team.index'))
        ->assertRedirect(route('merchant.select'));

    $inactiveMerchant = Merchant::factory()->create(['status' => MerchantStatus::Inactive]);
    $activeOwner = User::factory()->create();
    teamMembership($activeOwner, $inactiveMerchant, Role::Owner);

    $this->actingAs($activeOwner)
        ->withSession(teamSession($inactiveMerchant))
        ->get(route('merchant.team.index'))
        ->assertRedirect(route('merchant.select'));
});

test('last active owner cannot be removed deactivated or demoted', function () {
    ['merchant' => $merchant, 'owner' => $owner, 'membership' => $ownerMembership] = makeMerchantWithOwner();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $ownerMembership), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->patch(route('merchant.team.update', $ownerMembership), [
            'role' => Role::Owner->value,
            'status' => MembershipStatus::Inactive->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->delete(route('merchant.team.destroy', $ownerMembership))
        ->assertForbidden();

    expect($ownerMembership->fresh()->role)->toBe(Role::Owner)
        ->and($ownerMembership->fresh()->status)->toBe(MembershipStatus::Active);
});

test('merchant team routes do not expose platform users management while admin users still work', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->get(route('users.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Users/UsersPage', false));
});

test('owner and manager can manage business activities while staff is read only', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $staff = User::factory()->create();
    teamMembership($owner, $merchant, Role::Owner);
    teamMembership($manager, $merchant, Role::Manager);
    teamMembership($staff, $merchant, Role::Staff);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->get(route('merchant.activities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantBusinessActivitiesPage', false)
            ->where('canManageActivities', true)
        );

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.activities.store'), [
            'category_id' => $category->public_id,
            'merchant_id' => 999999,
        ])
        ->assertSessionHasErrors('merchant_id');

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.activities.store'), [
            'category_id' => $category->public_id,
        ])
        ->assertRedirect();

    $assignment = MerchantCategory::query()
        ->where('merchant_id', $merchant->id)
        ->where('category_id', $category->id)
        ->first();

    expect($assignment)->not->toBeNull();

    $this->actingAs($manager)
        ->withSession(teamSession($merchant))
        ->get(route('merchant.activities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManageActivities', true));

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->get(route('merchant.activities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManageActivities', false));

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.activities.store'), [
            'category_id' => Category::factory()->create()->public_id,
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession(teamSession($merchant))
        ->delete(route('merchant.activities.destroy', $assignment))
        ->assertForbidden();

    $foreignMerchant = Merchant::factory()->create();
    $foreignAssignment = MerchantCategory::factory()->create([
        'merchant_id' => $foreignMerchant->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->delete(route('merchant.activities.destroy', $foreignAssignment))
        ->assertNotFound();
});

test('owner cannot assign merchant-owner role through team self-service', function () {
    ['merchant' => $merchant, 'owner' => $owner] = makeMerchantWithOwner();

    $this->actingAs($owner)
        ->withSession(teamSession($merchant))
        ->post(route('merchant.team.store'), [
            'email' => 'another-owner@example.com',
            'name' => 'Another Owner',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'role' => Role::Owner->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertForbidden();
});
