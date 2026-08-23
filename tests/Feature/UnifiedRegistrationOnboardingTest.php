<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use Inertia\Testing\AssertableInertia as Assert;

test('guest can view unified registration', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterPage', false));
});

test('registration creates only a user then logs in to get started', function () {
    $usersBefore = User::query()->count();
    $customersBefore = Customer::query()->count();
    $merchantsBefore = Merchant::query()->count();
    $membershipsBefore = MerchantUser::query()->count();

    $this->post(route('register.store'), [
        'name' => 'Unified User',
        'email' => 'New.Owner@Example.TEST',
        'phone' => ' 0101234567 ',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('account.get-started'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'new.owner@example.test')->first();

    expect($user)->not->toBeNull()
        ->and(User::query()->count())->toBe($usersBefore + 1)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Merchant::query()->count())->toBe($merchantsBefore)
        ->and(MerchantUser::query()->count())->toBe($membershipsBefore)
        ->and($user->customer)->toBeNull()
        ->and($user->merchantMemberships)->toHaveCount(0)
        ->and($user->phone)->toBe('0101234567');

    $this->get(route('account.get-started'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Account/GetStartedPage', false));
});

test('duplicate users email registration is rejected without merging', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->post(route('register.store'), [
        'name' => 'Other',
        'email' => 'taken@example.test',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::query()->where('email', 'taken@example.test')->count())->toBe(1);
});

test('authenticated users cannot register a second account through register', function () {
    $user = User::factory()->create();
    $usersBefore = User::query()->count();

    $this->actingAs($user)
        ->get(route('register'))
        ->assertRedirect(route('account.get-started', absolute: false));

    $this->actingAs($user)
        ->post(route('register.store'), [
            'name' => 'Second',
            'email' => 'second@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertRedirect(route('account.get-started', absolute: false));

    expect(User::query()->count())->toBe($usersBefore)
        ->and(User::query()->where('email', 'second@example.test')->exists())->toBeFalse();
});

test('no capability user login goes to get started not customer portal', function () {
    $user = User::factory()->create(['email' => 'plain@example.test']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.get-started', absolute: false));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('account.get-started'));
});

test('request choice enables customer for the same user', function () {
    $user = User::factory()->create();
    $usersBefore = User::query()->count();

    $this->actingAs($user)
        ->from(route('account.get-started'))
        ->post(route('account.customer.enable.store'))
        ->assertRedirect(route('customer.requests.create'));

    expect(User::query()->count())->toBe($usersBefore)
        ->and($user->fresh()->customer)->not->toBeNull()
        ->and($user->fresh()->customer->user_id)->toBe($user->id)
        ->and(Customer::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('merchant user can add customer capability without a new user', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);
    $usersBefore = User::query()->count();
    $membershipsBefore = MerchantUser::query()->where('user_id', $user->id)->count();

    $this->actingAs($user)
        ->post(route('account.customer.enable.store'))
        ->assertRedirect(route('customer.requests.create'));

    expect(User::query()->count())->toBe($usersBefore)
        ->and($user->fresh()->customer?->user_id)->toBe($user->id)
        ->and(MerchantUser::query()->where('user_id', $user->id)->count())->toBe($membershipsBefore);
});

test('self service merchant onboarding requires auth', function () {
    $this->get(route('account.merchant.start'))->assertRedirect(route('login'));
    $this->post(route('account.merchant.start.store'), [
        'name' => 'Shop',
        'category_ids' => ['x'],
    ])->assertRedirect(route('login'));
});

test('self service merchant onboarding creates merchant owner for the same user', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $usersBefore = User::query()->count();

    $this->actingAs($user)
        ->get(route('account.merchant.start'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Account/StartMerchantPage', false));

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Self Serve Shop',
            'phone' => '0109999888',
            'email' => 'shop@example.test',
            'category_ids' => [$category->public_id],
            'owner_email' => 'someone-else@example.test',
            'owner_user_id' => 999,
            'user_id' => 999,
            'password' => 'not-allowed',
        ])
        ->assertSessionHasErrors(['owner_email', 'owner_user_id', 'user_id', 'password']);

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Self Serve Shop',
            'phone' => '0109999888',
            'email' => 'shop@example.test',
            'category_ids' => [$category->public_id],
        ])
        ->assertRedirect(route('merchant.home'))
        ->assertSessionHas(MerchantContextService::SESSION_KEY);

    $user->refresh();
    $merchant = Merchant::query()->where('name', 'Self Serve Shop')->first();

    expect(User::query()->count())->toBe($usersBefore)
        ->and($merchant)->not->toBeNull()
        ->and(MerchantUser::query()->where('user_id', $user->id)->where('merchant_id', $merchant->id)->where('role', Role::Owner)->exists())->toBeTrue()
        ->and(User::query()->where('email', 'someone-else@example.test')->exists())->toBeFalse()
        ->and($user->customer)->toBeNull();
});

test('customer user can start selling and keep customer on the same user', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);
    $category = Category::factory()->create();
    $usersBefore = User::query()->count();
    $customerId = $user->customer->id;

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Customer Shop',
            'category_ids' => [$category->public_id],
        ])
        ->assertRedirect(route('merchant.home'));

    $user->refresh();

    expect(User::query()->count())->toBe($usersBefore)
        ->and($user->customer?->id)->toBe($customerId)
        ->and($user->merchantMemberships()->where('role', Role::Owner)->count())->toBe(1)
        ->and($user->merchantMemberships()->where('status', MembershipStatus::Active)->count())->toBe(1);
});

test('merchant onboarding can create another business for the same user', function () {
    $user = User::factory()->create();
    $firstCategory = Category::factory()->create();
    $secondCategory = Category::factory()->create();

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Biz One',
            'category_ids' => [$firstCategory->public_id],
        ])
        ->assertRedirect(route('merchant.home'));

    $firstMerchantId = Merchant::query()->where('name', 'Biz One')->value('id');

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Biz Two',
            'category_ids' => [$secondCategory->public_id],
        ])
        ->assertRedirect(route('merchant.home'))
        ->assertSessionHas(MerchantContextService::SESSION_KEY, Merchant::query()->where('name', 'Biz Two')->value('id'));

    expect(MerchantUser::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(MerchantUser::query()->where('user_id', $user->id)->where('merchant_id', $firstMerchantId)->exists())->toBeTrue();
});
