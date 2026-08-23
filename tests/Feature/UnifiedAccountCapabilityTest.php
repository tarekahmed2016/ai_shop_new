<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\MerchantContextService;
use App\Services\MerchantService;
use App\Support\UserCapabilities;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function merchantOwnerUser(): array
{
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    return compact('user', 'merchant');
}

test('a user can have customer and merchant membership at the same time', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $customer = app(CustomerService::class)->ensureForUser($user);
    $category = Category::factory()->create();
    $merchant = app(MerchantService::class)->createForUser($user, [
        'name' => 'Dual Shop',
        'phone' => '0101111222',
        'email' => 'dual-shop@example.test',
        'category_ids' => [$category->public_id],
    ]);

    $user->refresh();
    $capabilities = UserCapabilities::for($user);

    expect(User::query()->count())->toBe(1)
        ->and($customer->user_id)->toBe($user->id)
        ->and($merchant->memberships()->where('user_id', $user->id)->where('role', Role::Owner)->exists())->toBeTrue()
        ->and($capabilities['hasCustomer'])->toBeTrue()
        ->and($capabilities['hasActiveCustomer'])->toBeTrue()
        ->and($capabilities['hasMerchantMemberships'])->toBeTrue()
        ->and($capabilities['hasActiveMerchantMemberships'])->toBeTrue()
        ->and($capabilities['merchantCount'])->toBe(1);
});

test('ensureForUser creates one customer for an existing user and is idempotent', function () {
    $user = User::factory()->create([
        'name' => 'Samir',
        'email' => 'samir@example.test',
        'phone' => '0105555666',
        'password' => 'keep-secret-1',
    ]);
    $usersBefore = User::query()->count();
    $password = $user->password;

    $first = app(CustomerService::class)->ensureForUser($user);
    $second = app(CustomerService::class)->ensureForUser($user->fresh());

    expect(User::query()->count())->toBe($usersBefore)
        ->and(Customer::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($first->id)->toBe($second->id)
        ->and($first->name)->toBe('Samir')
        ->and($first->email)->toBe('samir@example.test')
        ->and($first->phone)->toBe('0105555666')
        ->and($first->status)->toBe(CustomerStatus::Active)
        ->and($user->fresh()->password)->toBe($password)
        ->and(Hash::check('keep-secret-1', $user->fresh()->password))->toBeTrue();
});

test('ensureForUser does not reactivate an inactive customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->inactive()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Inactive,
    ]);

    $result = app(CustomerService::class)->ensureForUser($user);

    expect($result->id)->toBe($customer->id)
        ->and($result->status)->toBe(CustomerStatus::Inactive)
        ->and(Customer::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('ensureForUser does not attach a historical unlinked customer with the same email', function () {
    $user = User::factory()->create([
        'email' => 'shared@example.test',
        'phone' => '0107777888',
    ]);
    $historical = Customer::factory()->create([
        'user_id' => null,
        'email' => 'shared@example.test',
        'phone' => '0107777888',
        'name' => 'Legacy WhatsApp',
    ]);

    $created = app(CustomerService::class)->ensureForUser($user);

    expect($created->id)->not->toBe($historical->id)
        ->and($historical->fresh()->user_id)->toBeNull()
        ->and($created->user_id)->toBe($user->id)
        ->and(Customer::query()->where('email', 'shared@example.test')->count())->toBe(2);
});

test('merchant-only user can enable customer capability without losing merchant membership', function () {
    ['user' => $user, 'merchant' => $merchant] = merchantOwnerUser();
    $usersBefore = User::query()->count();

    $this->actingAs($user)
        ->get(route('customer.requests.create'))
        ->assertRedirect(route('account.customer.enable'));

    $this->actingAs($user)
        ->get(route('account.customer.enable'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Account/EnableCustomerPage', false));

    $this->actingAs($user)
        ->post(route('account.customer.enable.store'))
        ->assertRedirect();

    $user->refresh();

    expect(User::query()->count())->toBe($usersBefore)
        ->and($user->customer)->not->toBeNull()
        ->and($user->customer->user_id)->toBe($user->id)
        ->and($user->merchantMemberships()->where('merchant_id', $merchant->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    $ownRequest = CustomerRequest::factory()->create(['customer_id' => $user->customer->id]);
    $foreignRequest = CustomerRequest::factory()->create();

    expect($user->can('createOwn', CustomerRequest::class))->toBeTrue()
        ->and($user->can('viewOwn', $ownRequest))->toBeTrue()
        ->and($user->can('viewOwn', $foreignRequest))->toBeFalse();
});

test('createForUser makes the current user merchant owner without creating a user', function () {
    $user = User::factory()->create([
        'email' => 'owner-keep@example.test',
        'password' => 'stable-pass-9',
    ]);
    app(CustomerService::class)->ensureForUser($user);
    $this->actingAs($user);
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $usersBefore = User::query()->count();
    $email = $user->email;
    $password = $user->password;

    $first = app(MerchantService::class)->createForUser($user, [
        'name' => 'First Biz',
        'category_ids' => [$categoryA->public_id],
    ]);
    $second = app(MerchantService::class)->createForUser($user, [
        'name' => 'Second Biz',
        'category_ids' => [$categoryB->public_id],
    ]);

    $user->refresh();

    expect(User::query()->count())->toBe($usersBefore)
        ->and($user->email)->toBe($email)
        ->and($user->password)->toBe($password)
        ->and($first->id)->not->toBe($second->id)
        ->and($first->status)->toBe(MerchantStatus::Active)
        ->and(MerchantUser::query()->where('user_id', $user->id)->where('role', Role::Owner)->count())->toBe(2)
        ->and($user->customer)->not->toBeNull()
        ->and(UserCapabilities::for($user)['merchantCount'])->toBe(2);

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $first->id])
        ->get(route('merchant.home'))
        ->assertOk();

    $this->actingAs($user)
        ->withSession([MerchantContextService::SESSION_KEY => $second->id])
        ->get(route('merchant.home'))
        ->assertOk();
});

test('customer-only login goes to customer home while merchant-only and dual go to dashboard', function () {
    $customerUser = User::factory()->create(['email' => 'only-customer@example.test']);
    Customer::factory()->create([
        'user_id' => $customerUser->id,
        'status' => CustomerStatus::Active,
    ]);

    $this->post('/login', [
        'email' => $customerUser->email,
        'password' => 'password',
    ])->assertRedirect(route('customer.home', absolute: false));
    $this->post('/logout');

    ['user' => $merchantUser] = merchantOwnerUser();
    $this->post('/login', [
        'email' => $merchantUser->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
    $this->post('/logout');

    $dual = User::factory()->create(['email' => 'dual@example.test']);
    Customer::factory()->create([
        'user_id' => $dual->id,
        'status' => CustomerStatus::Active,
    ]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $dual->id,
        'merchant_id' => Merchant::factory(),
        'status' => MembershipStatus::Active,
    ]);

    $this->post('/login', [
        'email' => $dual->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($dual->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage', false)
            ->where('auth.capabilities.hasCustomer', true)
            ->where('auth.capabilities.hasMerchantMemberships', true));
});

test('platform admin still lands on dashboard even with customer or merchant capabilities', function () {
    $admin = User::factory()->create(['email' => 'admin-cap@example.test']);
    $admin->assignRole('admin');
    Customer::factory()->create(['user_id' => $admin->id]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $admin->id,
        'merchant_id' => Merchant::factory(),
    ]);

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($admin->fresh())
        ->get(route('dashboard'))
        ->assertOk();
});

test('inactive user customer and merchant protections remain', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactive-cap@example.test',
        'status' => UserStatus::Inactive,
    ]);
    $this->post('/login', [
        'email' => $inactiveUser->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    $user = User::factory()->create();
    Customer::factory()->inactive()->create(['user_id' => $user->id]);
    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertForbidden();

    ['user' => $merchantUser, 'merchant' => $merchant] = merchantOwnerUser();
    $merchant->update(['status' => MerchantStatus::Inactive]);
    $this->actingAs($merchantUser)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->get(route('merchant.home'))
        ->assertRedirect(route('merchant.select'));
});

test('enable customer ignores forged customer and user ids', function () {
    $user = User::factory()->create();
    $other = Customer::factory()->create(['user_id' => null]);

    $this->actingAs($user)
        ->post(route('account.customer.enable.store'), [
            'customer_id' => $other->id,
            'user_id' => 999999,
        ])
        ->assertRedirect(route('customer.requests.create'));

    expect($other->fresh()->user_id)->toBeNull()
        ->and($user->fresh()->customer?->id)->not->toBe($other->id)
        ->and(Customer::query()->where('user_id', $user->id)->count())->toBe(1);
});
