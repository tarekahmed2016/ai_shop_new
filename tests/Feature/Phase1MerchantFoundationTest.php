<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

function attachUserToMerchant(User $user, Merchant $merchant, Role $role = Role::Staff, MembershipStatus $status = MembershipStatus::Active): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

test('platform admin can create a merchant with unique public_id', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), [
            'name' => 'Alpha Shop',
            'phone' => '0123456789',
            'email' => 'alpha@example.com',
            'status' => MerchantStatus::Active->value,
            'category_ids' => [$category->public_id],
            'owner_name' => 'Alpha Owner',
            'owner_email' => 'alpha-owner@example.com',
            'owner_phone' => '0111111111',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertRedirect();

    $merchant = Merchant::query()->where('name', 'Alpha Shop')->first();

    expect($merchant)->not->toBeNull()
        ->and($merchant->public_id)->not->toBeEmpty()
        ->and(Str::isUlid($merchant->public_id))->toBeTrue()
        ->and(ActivityLog::where('event', Event::Created)->where('subject_id', $merchant->id)->exists())->toBeTrue();
});

test('platform admin can update merchant including inactive status', function () {
    $merchant = Merchant::factory()->create(['name' => 'Before']);

    $this->actingAs($this->admin)
        ->put(route('merchants.update', $merchant), [
            'name' => 'After',
            'phone' => $merchant->phone,
            'email' => $merchant->email,
            'status' => MerchantStatus::Inactive->value,
        ])
        ->assertRedirect();

    expect($merchant->fresh()->name)->toBe('After')
        ->and($merchant->fresh()->status)->toBe(MerchantStatus::Inactive);
});

test('non admin cannot manage merchants', function () {
    $this->actingAs($this->user)
        ->get(route('merchants.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->post(route('merchants.store'), [
            'name' => 'Blocked',
            'status' => MerchantStatus::Active->value,
        ])
        ->assertRedirect(route('login'));
});

test('user can belong to merchant A and merchant B', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();

    attachUserToMerchant($this->user, $merchantA, Role::Owner);
    attachUserToMerchant($this->user, $merchantB, Role::Staff);

    expect($this->user->merchants()->count())->toBe(2);
});

test('duplicate user merchant membership is rejected', function () {
    $merchant = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchant);

    $this->actingAs($this->admin)
        ->post(route('merchants.memberships.store', $merchant), [
            'user_id' => $this->user->id,
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertSessionHasErrors('user_id');

    expect(MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $this->user->id)->count())->toBe(1);
});

test('user can activate a merchant they belong to', function () {
    $merchant = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchant, Role::Manager);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($this->user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantHomePage', false)
            ->where('merchant.public_id', $merchant->public_id));
});

test('user cannot activate a merchant they do not belong to', function () {
    $merchant = Merchant::factory()->create();

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertForbidden();
});

test('forged public_id does not grant access', function () {
    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => (string) Str::ulid()])
        ->assertForbidden();
});

test('forged merchant_id in session has no authorization effect', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchantA);

    $this->actingAs($this->user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchantB->id])
        ->get(route('merchant.home'))
        ->assertRedirect(route('merchant.select'));
});

test('inactive membership is rejected for merchant access', function () {
    $merchant = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchant, Role::Staff, MembershipStatus::Inactive);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertForbidden();
});

test('inactive merchant cannot become active context', function () {
    $merchant = Merchant::factory()->inactive()->create();
    attachUserToMerchant($this->user, $merchant);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertForbidden();
});

test('merchant context is rejected when membership becomes invalid', function () {
    $merchant = Merchant::factory()->create();
    $membership = attachUserToMerchant($this->user, $merchant);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertRedirect(route('merchant.home'));

    $membership->update(['status' => MembershipStatus::Inactive]);

    $this->actingAs($this->user)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->get(route('merchant.home'))
        ->assertRedirect(route('merchant.select'));
});

test('membership in merchant A cannot grant merchant B access', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchantA, Role::Owner);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantB->public_id])
        ->assertForbidden();
});

test('merchant role in A does not leak to B', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchantA, Role::Owner);
    attachUserToMerchant($this->user, $merchantB, Role::Staff);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantA->public_id]);

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantA->id);

    $this->actingAs($this->user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantB->public_id])
        ->assertRedirect(route('merchant.home'));

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantB->id)
        ->and($this->user->merchantMemberships()->where('merchant_id', $merchantB->id)->first()->role)->toBe(Role::Staff)
        ->and($this->user->hasRole('admin'))->toBeFalse();
});

test('merchant role does not grant cms platform admin access', function () {
    $merchant = Merchant::factory()->create();
    attachUserToMerchant($this->user, $merchant, Role::Owner);

    $this->actingAs($this->user)
        ->get(route('users.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->get(route('roles.index'))
        ->assertRedirect(route('login'));
});

test('existing admin role still works', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard/IndexPage', false));

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Merchants/MerchantsPage', false));
});

test('public site remains available', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/HomePage', false));
});

test('client cannot mass assign public_id', function () {
    $forged = (string) Str::ulid();
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), [
            'name' => 'Secure Shop',
            'status' => MerchantStatus::Active->value,
            'public_id' => $forged,
            'category_ids' => [$category->public_id],
            'owner_name' => 'Secure Owner',
            'owner_email' => 'secure-owner@example.com',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertRedirect();

    $merchant = Merchant::query()->where('name', 'Secure Shop')->first();

    expect($merchant->public_id)->not->toBe($forged);
});

test('admin can attach update and remove memberships with activity logs', function () {
    $merchant = Merchant::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.memberships.store', $merchant), [
            'user_id' => $this->user->id,
            'role' => Role::Manager->value,
            'status' => MembershipStatus::Active->value,
        ])
        ->assertRedirect();

    $membership = MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $this->user->id)->first();

    expect($membership)->not->toBeNull()
        ->and(ActivityLog::where('event', Event::Created)->where('subject_id', $membership->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('merchants.memberships.update', [$merchant, $membership]), [
            'role' => Role::Staff->value,
            'status' => MembershipStatus::Inactive->value,
        ])
        ->assertRedirect();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Inactive);

    $this->actingAs($this->admin)
        ->delete(route('merchants.memberships.destroy', [$merchant, $membership]))
        ->assertRedirect();

    expect(MerchantUser::find($membership->id))->toBeNull();
});

test('membership routes reject cross merchant ids', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $membership = attachUserToMerchant($this->user, $merchantA);

    $this->actingAs($this->admin)
        ->delete(route('merchants.memberships.destroy', [$merchantB, $membership]))
        ->assertNotFound();
});
