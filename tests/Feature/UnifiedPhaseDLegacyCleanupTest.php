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

test('journey a guest register then enable customer creates one user', function () {
    $usersBefore = User::query()->count();

    $this->post(route('register.store'), [
        'name' => 'Journey A',
        'email' => 'journey-a@example.test',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('account.get-started'));

    $user = User::query()->where('email', 'journey-a@example.test')->first();

    $this->actingAs($user)
        ->post(route('account.customer.enable.store'))
        ->assertRedirect(route('customer.requests.create'));

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    expect(User::query()->count())->toBe($usersBefore + 1)
        ->and($user->fresh()->customer?->user_id)->toBe($user->id)
        ->and($user->fresh()->merchantMemberships)->toHaveCount(0);
});

test('journey b guest register then start selling creates one owner user', function () {
    $usersBefore = User::query()->count();
    $category = Category::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'Journey B',
        'email' => 'journey-b@example.test',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('account.get-started'));

    $user = User::query()->where('email', 'journey-b@example.test')->first();

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Journey B Shop',
            'category_ids' => [$category->public_id],
        ])
        ->assertRedirect(route('merchant.home'))
        ->assertSessionHas(MerchantContextService::SESSION_KEY);

    expect(User::query()->count())->toBe($usersBefore + 1)
        ->and($user->fresh()->customer)->toBeNull()
        ->and(MerchantUser::query()->where('user_id', $user->id)->where('role', Role::Owner)->count())->toBe(1);
});

test('legacy customer register get is compatibility redirect not a competing page', function () {
    $this->get('/customer/register')
        ->assertRedirect(route('register'));

    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterPage', false));
});

test('legacy customer register post uses unified user-only registration', function () {
    $customersBefore = Customer::query()->count();

    $this->post('/customer/register', [
        'name' => 'Legacy Post',
        'email' => 'legacy-post@example.test',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('account.get-started'));

    $user = User::query()->where('email', 'legacy-post@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->customer)->toBeNull()
        ->and(Customer::query()->count())->toBe($customersBefore);
});

test('historical unlinked customer is not auto-linked when enabling customer', function () {
    $user = User::factory()->create(['email' => 'same@example.test']);
    $historical = Customer::factory()->create([
        'user_id' => null,
        'email' => 'same@example.test',
        'phone' => '0101111222',
        'status' => CustomerStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('account.customer.enable.store'))
        ->assertRedirect(route('customer.requests.create'));

    expect($historical->fresh()->user_id)->toBeNull()
        ->and($user->fresh()->customer?->id)->not->toBe($historical->id);
});

test('inactive merchant cannot be selected as workspace', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->inactive()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertForbidden();
});

test('inactive membership cannot access merchant workspace', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Inactive,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertForbidden();
});

test('foreign merchant public id cannot activate context', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $other->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), [
            'public_id' => $merchant->public_id,
            'merchant_id' => $merchant->id,
            'user_id' => $other->id,
        ])
        ->assertForbidden();
});
