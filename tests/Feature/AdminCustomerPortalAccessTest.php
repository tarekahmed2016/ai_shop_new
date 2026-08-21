<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin cannot create a customer without creating a user', function () {
    $beforeUsers = User::query()->count();
    $beforeCustomers = Customer::query()->count();

    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'No Password Customer',
            'phone' => '01000000001',
            'email' => 'no-password@example.test',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe($beforeUsers)
        ->and(Customer::query()->count())->toBe($beforeCustomers);
});

test('admin-created customer always has linked hashed user without admin or merchant grants', function () {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'Admin Created',
            'phone' => '01000000002',
            'email' => 'admin-created@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('email', 'admin-created@example.test')->first();
    $user = User::query()->where('email', 'admin-created@example.test')->first();

    expect($customer)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and($customer->user_id)->toBe($user->id)
        ->and($user->customer?->id)->toBe($customer->id)
        ->and(Hash::check('password12', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('password12')
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and($user->roles()->count())->toBe(0)
        ->and(MerchantUser::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->getAllPermissions()->count())->toBe(0);
});

test('frontend user_id is rejected on admin create and portal enable', function () {
    $historical = Customer::factory()->create([
        'user_id' => null,
        'email' => 'needs-login@example.test',
    ]);

    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'Injected',
            'phone' => '01000000009',
            'email' => 'injected-user-id@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'status' => CustomerStatus::Active->value,
            'user_id' => 999999,
        ])
        ->assertSessionHasErrors('user_id');

    $this->actingAs($this->admin)
        ->post(route('customers.portal-access', $historical), [
            'name' => 'Historical',
            'email' => 'needs-login@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'user_id' => 999999,
        ])
        ->assertSessionHasErrors('user_id');

    expect($historical->fresh()->user_id)->toBeNull()
        ->and(Customer::query()->where('email', 'injected-user-id@example.test')->exists())->toBeFalse();
});

test('duplicate users email is rejected and never auto-links existing accounts', function () {
    $existing = User::factory()->create(['email' => 'existing-user@example.test']);
    $existing->assignRole('admin');

    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->create([
        'merchant_id' => $merchant->id,
        'user_id' => $existing->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $beforeCustomers = Customer::query()->count();

    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'Should Fail',
            'phone' => '01000000003',
            'email' => 'existing-user@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionHasErrors('email');

    expect(Customer::query()->count())->toBe($beforeCustomers)
        ->and(Customer::query()->where('user_id', $existing->id)->exists())->toBeFalse()
        ->and($existing->fresh()->hasRole('admin'))->toBeTrue();
});

test('transaction rolls back user when customer creation fails', function () {
    $beforeUsers = User::query()->count();
    $beforeCustomers = Customer::query()->count();

    expect(fn () => app(CustomerService::class)->store([
        'name' => 'Rollback Customer',
        'phone' => '01000000004',
        'email' => 'rollback-customer@example.test',
        'password' => 'password12',
        'status' => 999,
    ]))->toThrow(ValueError::class);

    expect(User::query()->count())->toBe($beforeUsers)
        ->and(Customer::query()->count())->toBe($beforeCustomers)
        ->and(User::query()->where('email', 'rollback-customer@example.test')->exists())->toBeFalse();
});

test('historical unlinked customers remain unchanged until explicit portal enable', function () {
    $historical = Customer::factory()->create([
        'user_id' => null,
        'name' => 'Historical',
        'email' => 'historical@example.test',
        'phone' => '01000000005',
        'status' => CustomerStatus::Active,
    ]);

    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertOk();

    expect($historical->fresh()->user_id)->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('customers.portal-access', $historical), [
            'name' => 'Historical',
            'email' => 'historical@example.test',
            'phone' => '01000000005',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertRedirect();

    $historical->refresh();
    $user = User::query()->where('email', 'historical@example.test')->first();

    expect($historical->user_id)->toBe($user->id)
        ->and(Hash::check('password12', $user->password))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and(MerchantUser::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('explicit portal enable never auto-links an existing user by email', function () {
    $existing = User::factory()->create(['email' => 'taken-portal@example.test']);
    $historical = Customer::factory()->create([
        'user_id' => null,
        'email' => 'other-historical@example.test',
    ]);

    $this->actingAs($this->admin)
        ->post(route('customers.portal-access', $historical), [
            'name' => 'Historical',
            'email' => 'taken-portal@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertSessionHasErrors('email');

    expect($historical->fresh()->user_id)->toBeNull()
        ->and(Customer::query()->where('user_id', $existing->id)->exists())->toBeFalse();
});

test('admin-created customer can log in and access customer portal with own requests only', function () {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'Portal Ready',
            'phone' => '01000000006',
            'email' => 'portal-ready@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('email', 'portal-ready@example.test')->first();
    $user = User::query()->where('email', 'portal-ready@example.test')->first();
    $other = Customer::factory()->create();

    $own = CustomerRequest::factory()->create(['customer_id' => $customer->id]);
    $foreign = CustomerRequest::factory()->create(['customer_id' => $other->id]);

    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => 'portal-ready@example.test',
        'password' => 'password12',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);

    $this->get(route('customer.home'))
        ->assertOk();

    $this->get(route('customer.requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CustomerPortal/RequestsIndexPage', false)
            ->has('requests.data', 1)
            ->where('requests.data.0.public_id', $own->public_id)
        );

    $this->get(route('customer.requests.show', $foreign))
        ->assertNotFound();
});

test('self-registration still works after admin create changes', function () {
    $this->post(route('customer.register.store'), [
        'name' => 'Self Reg',
        'email' => 'self-reg-still@example.test',
        'phone' => '01000000007',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('customer.home'));

    $user = User::query()->where('email', 'self-reg-still@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->customer)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeFalse();
});

test('non-admin cannot manage customers or enable portal access', function () {
    $user = User::factory()->create();
    $historical = Customer::factory()->create(['user_id' => null]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'Nope',
            'phone' => '01000000008',
            'email' => 'nope@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->post(route('customers.portal-access', $historical), [
            'name' => 'Nope',
            'email' => 'nope-portal@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertRedirect(route('login'));
});
