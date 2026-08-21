<?php

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MerchantContextService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create([
        'name' => 'Platform Admin',
        'email' => 'admin-profile@example.test',
        'phone' => '90001111',
    ]);
    $this->admin->assignRole('admin');

    $this->merchantOwner = User::factory()->create([
        'name' => 'Merchant Owner',
        'email' => 'owner-profile@example.test',
        'phone' => '90002222',
    ]);

    $this->merchantStaff = User::factory()->create([
        'name' => 'Merchant Staff',
        'email' => 'staff-profile@example.test',
        'phone' => '90003333',
    ]);

    $this->merchant = Merchant::factory()->create(['name' => 'Dashboard Demo Shop']);
    $category = Category::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $this->merchant->id,
        'category_id' => $category->id,
    ]);

    MerchantUser::factory()->create([
        'user_id' => $this->merchantOwner->id,
        'merchant_id' => $this->merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    MerchantUser::factory()->create([
        'user_id' => $this->merchantStaff->id,
        'merchant_id' => $this->merchant->id,
        'role' => Role::Staff,
        'status' => MembershipStatus::Active,
    ]);

    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $this->merchant->id,
        'status' => MatchStatus::Pending,
    ]);
    RequestMatch::factory()->viewed()->create([
        'customer_request_id' => CustomerRequest::factory()->create(['category_id' => $category->id])->id,
        'merchant_id' => $this->merchant->id,
    ]);
});

function merchantSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

test('platform admin still sees cms dashboard quick links', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage', false)
            ->where('isAdmin', true)
            ->where('merchantWorkspace', null));
});

test('merchant owner does not see cms dashboard links and sees merchant workspace stats', function () {
    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage', false)
            ->where('isAdmin', false)
            ->where('merchantWorkspace.name', 'Dashboard Demo Shop')
            ->where('merchantWorkspace.role', Role::Owner->value)
            ->where('merchantWorkspace.categories_count', 1)
            ->where('merchantWorkspace.available_requests_count', 2)
            ->where('merchantWorkspace.viewed_requests_count', 1));
});

test('merchant user cannot directly access cms admin routes', function () {
    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('company-info.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('services.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('contact-messages.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('customers.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('matching.index'))
        ->assertRedirect(route('login'));
});

test('authenticated admin and merchant users can open own profile', function () {
    $this->actingAs($this->admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/EditPage', false)
            ->where('user.email', 'admin-profile@example.test'));

    $this->actingAs($this->merchantOwner)
        ->withSession(merchantSession($this->merchant))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/EditPage', false)
            ->where('user.email', 'owner-profile@example.test')
            ->where('merchantContext.name', 'Dashboard Demo Shop')
            ->where('merchantContext.role', Role::Owner->value));

    $this->actingAs($this->merchantStaff)
        ->withSession(merchantSession($this->merchant))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.email', 'staff-profile@example.test')
            ->where('merchantContext.role', Role::Staff->value));
});

test('user can update own name email and phone only', function () {
    $this->actingAs($this->merchantOwner)
        ->patch(route('profile.update'), [
            'name' => 'Updated Owner',
            'email' => 'updated-owner@example.test',
            'phone' => '90009999',
            'status' => 2,
            'role' => 'admin',
            'password' => 'hacked',
            'id' => $this->admin->id,
            'user_id' => $this->admin->id,
        ])
        ->assertRedirect(route('profile.edit'));

    $this->merchantOwner->refresh();

    expect($this->merchantOwner->name)->toBe('Updated Owner')
        ->and($this->merchantOwner->email)->toBe('updated-owner@example.test')
        ->and($this->merchantOwner->phone)->toBe('90009999')
        ->and($this->merchantOwner->hasRole('admin'))->toBeFalse()
        ->and(Hash::check('password', $this->merchantOwner->password))->toBeTrue();

    expect($this->admin->fresh()->email)->toBe('admin-profile@example.test');
});

test('user cannot modify another users profile through payload identity fields', function () {
    $victim = User::factory()->create([
        'name' => 'Victim User',
        'email' => 'victim@example.test',
        'phone' => '90004444',
    ]);

    $this->actingAs($this->merchantStaff)
        ->patch(route('profile.update'), [
            'id' => $victim->id,
            'user_id' => $victim->id,
            'name' => 'Hacked Victim',
            'email' => 'hacked-victim@example.test',
            'phone' => '11111111',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($victim->fresh()->name)->toBe('Victim User')
        ->and($victim->fresh()->email)->toBe('victim@example.test')
        ->and($this->merchantStaff->fresh()->name)->toBe('Hacked Victim');
});

test('user cannot escalate status or merchant membership through profile payload', function () {
    $membership = MerchantUser::query()
        ->where('user_id', $this->merchantStaff->id)
        ->where('merchant_id', $this->merchant->id)
        ->first();

    $this->actingAs($this->merchantStaff)
        ->patch(route('profile.update'), [
            'name' => $this->merchantStaff->name,
            'email' => $this->merchantStaff->email,
            'phone' => $this->merchantStaff->phone,
            'status' => 2,
            'role' => Role::Owner->value,
            'merchant_id' => 999,
            'membership_id' => $membership->id,
        ])
        ->assertRedirect(route('profile.edit'));

    $membership->refresh();

    expect($this->merchantStaff->fresh()->status->value)->toBe(1)
        ->and($membership->role)->toBe(Role::Staff)
        ->and($membership->merchant_id)->toBe($this->merchant->id);
});

test('password update works and confirmation is required', function () {
    $this->actingAs($this->merchantOwner)
        ->from(route('profile.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-password12',
            'password_confirmation' => 'new-password12',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('new-password12', $this->merchantOwner->fresh()->password))->toBeTrue();

    $this->actingAs($this->merchantOwner)
        ->from(route('profile.edit'))
        ->put(route('password.update'), [
            'current_password' => 'new-password12',
            'password' => 'another-pass12',
            'password_confirmation' => 'mismatch-pass12',
        ])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));
});

test('profile route exists and settings cms route remains admin only', function () {
    expect(route('profile.edit'))->toContain('/profile');

    $this->actingAs($this->admin)
        ->get(route('company-info.index'))
        ->assertOk();

    $this->actingAs($this->merchantOwner)
        ->get(route('company-info.index'))
        ->assertRedirect(route('login'));
});
