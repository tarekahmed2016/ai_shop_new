<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function workspaceOwner(array $userAttrs = []): array
{
    $user = User::factory()->create($userAttrs);
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    return compact('user', 'merchant');
}

function workspaceCustomer(User $user, CustomerStatus $status = CustomerStatus::Active): Customer
{
    return Customer::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => $status,
    ]);
}

test('customer-only user can open the customer workspace', function () {
    $user = User::factory()->create();
    workspaceCustomer($user);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('CustomerPortal/HomePage', false));
});

test('merchant-only user can open the merchant workspace', function () {
    ['user' => $user, 'merchant' => $merchant] = workspaceOwner();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk();
});

test('dual user can access customer and merchant workspaces without a second login', function () {
    ['user' => $user, 'merchant' => $merchant] = workspaceOwner();
    workspaceCustomer($user);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('switching merchant A to merchant B activates merchant B', function () {
    $user = User::factory()->create();
    $merchantA = Merchant::factory()->create(['name' => 'Biz A']);
    $merchantB = Merchant::factory()->create(['name' => 'Biz B']);
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantA->id,
        'status' => MembershipStatus::Active,
    ]);
    MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantB->id,
        'role' => Role::Manager,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantA->public_id])
        ->assertRedirect(route('merchant.home'));

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantA->id);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantB->public_id])
        ->assertRedirect(route('merchant.home'));

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantB->id)
        ->and($user->merchantMemberships()->count())->toBe(2);
});

test('customer workspace does not require logout and keeps merchant context', function () {
    ['user' => $user, 'merchant' => $merchant] = workspaceOwner();
    workspaceCustomer($user);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id]);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchant->id);

    $this->actingAs($user)
        ->get(route('merchant.select'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableMerchants.0.public_id', $merchant->public_id)
            ->where('availableMerchants.0.current', true)
            ->missing('availableMerchants.0.id')
            ->missing('availableMerchants.0.merchant_id'));
});

test('unauthorized inactive and foreign merchants cannot be activated', function () {
    $user = User::factory()->create();
    $own = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $own->id,
        'status' => MembershipStatus::Active,
    ]);

    $foreign = Merchant::factory()->create();
    $otherUser = User::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $otherUser->id,
        'merchant_id' => $foreign->id,
        'status' => MembershipStatus::Active,
    ]);

    $inactiveMembershipMerchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $inactiveMembershipMerchant->id,
        'status' => MembershipStatus::Inactive,
    ]);

    $inactiveMerchant = Merchant::factory()->inactive()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $inactiveMerchant->id,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $foreign->public_id])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $inactiveMembershipMerchant->public_id])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $inactiveMerchant->public_id])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), [
            'merchant_id' => $foreign->id,
            'user_id' => $otherUser->id,
        ])
        ->assertSessionHasErrors('public_id');

    $this->actingAs($otherUser)
        ->post(route('merchant.context.store'), ['public_id' => $own->public_id])
        ->assertForbidden();

    expect(session(MerchantContextService::SESSION_KEY))->toBeNull();
});

test('customer ownership remains after merchant switch', function () {
    ['user' => $user, 'merchant' => $merchant] = workspaceOwner();
    $customer = workspaceCustomer($user);
    $ownRequest = CustomerRequest::factory()->create(['customer_id' => $customer->id]);
    $foreignRequest = CustomerRequest::factory()->create();

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id]);

    $this->actingAs($user)
        ->get(route('customer.requests.show', $ownRequest))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('customer.requests.show', $foreignRequest))
        ->assertNotFound();
});

test('start selling and create another business keep the same user', function () {
    $user = User::factory()->create();
    workspaceCustomer($user);
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $usersBefore = User::query()->count();

    $this->actingAs($user)
        ->get(route('account.merchant.start'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'First Shop',
            'category_ids' => [$categoryA->public_id],
        ])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->post(route('account.merchant.start.store'), [
            'name' => 'Second Shop',
            'category_ids' => [$categoryB->public_id],
        ])
        ->assertRedirect(route('merchant.home'));

    expect(User::query()->count())->toBe($usersBefore)
        ->and($user->fresh()->customer)->not->toBeNull()
        ->and(MerchantUser::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('admin dashboard and no-capability get-started behavior remain', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    workspaceCustomer($admin);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    $plain = User::factory()->create();
    $this->actingAs($plain)
        ->get(route('dashboard'))
        ->assertRedirect(route('account.get-started'));

    $this->actingAs($plain)
        ->get(route('customer.home'))
        ->assertRedirect(route('account.customer.enable'));
});

test('inactive customer cannot use the customer workspace', function () {
    $user = User::factory()->create();
    workspaceCustomer($user, CustomerStatus::Inactive);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertForbidden();
});

test('merchant notification deep link restores merchant context after customer browsing', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchantA->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchantB->id, 'category_id' => $category->id]);
    $user = User::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantA->id,
        'status' => MembershipStatus::Active,
    ]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantB->id,
        'status' => MembershipStatus::Active,
    ]);
    workspaceCustomer($user);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($request);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchantA->public_id]);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('merchant.requests.open', [
            'merchant' => $merchantB->public_id,
            'customerRequest' => $request->public_id,
        ]))
        ->assertRedirect(route('merchant.requests.show', $request));

    expect(session(MerchantContextService::SESSION_KEY))->toBe($merchantB->id);
});

test('customer offer deep link works after merchant workspace use', function () {
    ['user' => $user, 'merchant' => $merchant] = workspaceOwner();
    $customer = workspaceCustomer($user);
    $request = CustomerRequest::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id]);

    $this->actingAs($user)
        ->get(route('customer.requests.show', $request))
        ->assertOk();
});

test('push subscription stays owned by the same user across workspaces', function () {
    ['user' => $user, 'merchant' => $merchant] = workspaceOwner();
    workspaceCustomer($user);

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://push.example.com/workspace-switch',
        'public_key' => 'pk',
        'auth_token' => 'ak',
        'content_encoding' => 'aes128gcm',
    ]);

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id]);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk();

    expect($user->fresh()->pushSubscriptions()->count())->toBe(1)
        ->and($user->fresh()->pushSubscriptions()->first()->endpoint)->toBe('https://push.example.com/workspace-switch');
});
