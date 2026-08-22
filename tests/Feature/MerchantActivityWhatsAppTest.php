<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function activityWhatsAppSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function activityWhatsAppMembership(User $user, Merchant $merchant, Role $role): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => MembershipStatus::Active,
    ]);
}

function activityWhatsAppOfferSetup(array $overrides = []): array
{
    $categoryA = $overrides['categoryA'] ?? Category::factory()->create();
    $categoryB = $overrides['categoryB'] ?? Category::factory()->create();
    $merchant = Merchant::factory()->create([
        'phone' => $overrides['merchant_phone'] ?? '+968 90000000',
        'name' => 'ABC Merchant',
    ]);

    $assignmentA = MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $categoryA->id,
        'whatsapp_phone' => array_key_exists('phone_a', $overrides) ? $overrides['phone_a'] : '91111111',
    ]);
    $assignmentB = MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $categoryB->id,
        'whatsapp_phone' => array_key_exists('phone_b', $overrides) ? $overrides['phone_b'] : '92222222',
    ]);

    $owner = User::factory()->create(['phone' => '999000111']);
    activityWhatsAppMembership($owner, $merchant, Role::Owner);

    $customerUser = User::factory()->create(['phone' => '0100555666']);
    $customer = Customer::factory()->create([
        'user_id' => $customerUser->id,
        'phone' => '0100555666',
        'email' => $customerUser->email,
        'whatsapp_id' => 'wa-customer-'.(string) Str::ulid(),
        'name' => $customerUser->name,
    ]);

    $requestA = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $categoryA->id,
        'status' => RequestStatus::Ready,
    ]);
    $requestB = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $categoryB->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($requestA);
    app(RequestMatchingService::class)->sync($requestB);

    $offerA = MerchantOffer::factory()->create([
        'customer_request_id' => $requestA->id,
        'merchant_id' => $merchant->id,
        'price' => '10.000',
        'status' => OfferStatus::Submitted,
        'availability_status' => AvailabilityStatus::Available,
        'submitted_at' => now(),
    ]);
    $offerB = MerchantOffer::factory()->create([
        'customer_request_id' => $requestB->id,
        'merchant_id' => $merchant->id,
        'price' => '20.000',
        'status' => OfferStatus::Submitted,
        'availability_status' => AvailabilityStatus::Available,
        'submitted_at' => now(),
    ]);

    return compact(
        'merchant',
        'owner',
        'customerUser',
        'customer',
        'categoryA',
        'categoryB',
        'assignmentA',
        'assignmentB',
        'requestA',
        'requestB',
        'offerA',
        'offerB',
    );
}

test('migration adds nullable whatsapp_phone without rewriting merchant_categories rows', function () {
    $merchant = Merchant::factory()->create();
    $category = Category::factory()->create();
    $assignment = MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    expect(Schema::hasColumn('merchant_categories', 'whatsapp_phone'))->toBeTrue()
        ->and(Schema::hasTable('company_info'))->toBeTrue()
        ->and($assignment->fresh()->whatsapp_phone)->toBeNull()
        ->and($assignment->fresh()->merchant_id)->toBe($merchant->id)
        ->and($assignment->fresh()->category_id)->toBe($category->id);

    $column = collect(Schema::getColumns('merchant_categories'))
        ->firstWhere('name', 'whatsapp_phone');

    expect($column)->not->toBeNull()
        ->and((bool) $column['nullable'])->toBeTrue();
});

test('merchant can store different activity whatsapp numbers and update them', function () {
    $merchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    activityWhatsAppMembership($owner, $merchant, Role::Owner);
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();

    $this->actingAs($owner)
        ->withSession(activityWhatsAppSession($merchant))
        ->post(route('merchant.activities.store'), [
            'category_id' => $categoryA->public_id,
            'whatsapp_phone' => '91111111',
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->withSession(activityWhatsAppSession($merchant))
        ->post(route('merchant.activities.store'), [
            'category_id' => $categoryB->public_id,
            'whatsapp_phone' => '+96892222222',
        ])
        ->assertRedirect();

    $assignmentA = MerchantCategory::query()
        ->where('merchant_id', $merchant->id)
        ->where('category_id', $categoryA->id)
        ->first();
    $assignmentB = MerchantCategory::query()
        ->where('merchant_id', $merchant->id)
        ->where('category_id', $categoryB->id)
        ->first();

    expect($assignmentA?->whatsapp_phone)->toBe('91111111')
        ->and($assignmentB?->whatsapp_phone)->toBe('+96892222222')
        ->and($merchant->fresh()->phone)->not->toBe('91111111');

    $this->actingAs($owner)
        ->withSession(activityWhatsAppSession($merchant))
        ->get(route('merchant.activities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantBusinessActivitiesPage', false)
            ->where('assignments.data.0.whatsapp_phone', fn ($value) => in_array($value, ['91111111', '+96892222222'], true))
        );

    $originalAssignmentId = $assignmentA->id;
    $originalCategoryId = $assignmentA->category_id;

    $this->actingAs($owner)
        ->withSession(activityWhatsAppSession($merchant))
        ->patch(route('merchant.activities.update', $assignmentA), [
            'whatsapp_phone' => '0096893333333',
            'merchant_id' => 999999,
        ])
        ->assertSessionHasErrors('merchant_id');

    $this->actingAs($owner)
        ->withSession(activityWhatsAppSession($merchant))
        ->patch(route('merchant.activities.update', $assignmentA), [
            'whatsapp_phone' => '0096893333333',
            'category_id' => $categoryB->public_id,
        ])
        ->assertSessionHasErrors('category_id');

    expect($assignmentA->fresh()->category_id)->toBe($originalCategoryId)
        ->and($assignmentA->fresh()->whatsapp_phone)->toBe('91111111');

    $this->actingAs($owner)
        ->withSession(activityWhatsAppSession($merchant))
        ->patch(route('merchant.activities.update', $assignmentA), [
            'whatsapp_phone' => '0096893333333',
        ])
        ->assertRedirect();

    $updated = $assignmentA->fresh();

    expect($updated->whatsapp_phone)->toBe('0096893333333')
        ->and($updated->id)->toBe($originalAssignmentId)
        ->and($updated->category_id)->toBe($originalCategoryId)
        ->and($updated->merchant_id)->toBe($merchant->id)
        ->and($assignmentB->fresh()->whatsapp_phone)->toBe('+96892222222')
        ->and(MerchantCategory::query()->where('merchant_id', $merchant->id)->count())->toBe(2);
});

test('activities.manage is required to update activity whatsapp and merchant A cannot edit merchant B', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $ownerA = User::factory()->create();
    $manager = User::factory()->create();
    $staff = User::factory()->create();
    activityWhatsAppMembership($ownerA, $merchantA, Role::Owner);
    activityWhatsAppMembership($manager, $merchantA, Role::Manager);
    activityWhatsAppMembership($staff, $merchantA, Role::Staff);

    $assignmentA = MerchantCategory::factory()->create([
        'merchant_id' => $merchantA->id,
        'category_id' => Category::factory()->create()->id,
        'whatsapp_phone' => '91111111',
    ]);
    $assignmentB = MerchantCategory::factory()->create([
        'merchant_id' => $merchantB->id,
        'category_id' => Category::factory()->create()->id,
        'whatsapp_phone' => '92222222',
    ]);

    $this->actingAs($manager)
        ->withSession(activityWhatsAppSession($merchantA))
        ->patch(route('merchant.activities.update', $assignmentA), [
            'whatsapp_phone' => '93333333',
        ])
        ->assertRedirect();

    expect($assignmentA->fresh()->whatsapp_phone)->toBe('93333333');

    $this->actingAs($staff)
        ->withSession(activityWhatsAppSession($merchantA))
        ->patch(route('merchant.activities.update', $assignmentA), [
            'whatsapp_phone' => '94444444',
        ])
        ->assertForbidden();

    expect($assignmentA->fresh()->whatsapp_phone)->toBe('93333333');

    $this->actingAs($ownerA)
        ->withSession(activityWhatsAppSession($merchantA))
        ->patch(route('merchant.activities.update', $assignmentB), [
            'whatsapp_phone' => '95555555',
        ])
        ->assertNotFound();

    expect($assignmentB->fresh()->whatsapp_phone)->toBe('92222222');
});

test('activity whatsapp does not change exact-category matching', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $categoryA->id,
        'whatsapp_phone' => '91111111',
    ]);
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $categoryB->id,
        'whatsapp_phone' => null,
    ]);

    $requestA = CustomerRequest::factory()->create([
        'category_id' => $categoryA->id,
        'status' => RequestStatus::Ready,
    ]);
    $requestB = CustomerRequest::factory()->create([
        'category_id' => $categoryB->id,
        'status' => RequestStatus::Ready,
    ]);
    $unrelated = Category::factory()->create();
    $requestC = CustomerRequest::factory()->create([
        'category_id' => $unrelated->id,
        'status' => RequestStatus::Ready,
    ]);

    app(RequestMatchingService::class)->sync($requestA);
    app(RequestMatchingService::class)->sync($requestB);
    app(RequestMatchingService::class)->sync($requestC);

    expect(RequestMatch::query()->where('customer_request_id', $requestA->id)->where('merchant_id', $merchant->id)->exists())->toBeTrue()
        ->and(RequestMatch::query()->where('customer_request_id', $requestB->id)->where('merchant_id', $merchant->id)->exists())->toBeTrue()
        ->and(RequestMatch::query()->where('customer_request_id', $requestC->id)->where('merchant_id', $merchant->id)->exists())->toBeFalse();
});

test('submitted offers use activity whatsapp with merchant phone fallback and never users.phone', function () {
    $setup = activityWhatsAppOfferSetup();

    $this->actingAs($setup['customerUser'])
        ->get(route('customer.requests.show', $setup['requestA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.whatsapp_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96891111111?text='))
            ->missing('offers.0.phone')
            ->missing('offers.0.merchant_categories')
        );

    $this->actingAs($setup['customerUser'])
        ->get(route('customer.requests.show', $setup['requestB']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.whatsapp_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96892222222?text='))
        );

    $setup['assignmentA']->update(['whatsapp_phone' => null]);

    $this->actingAs($setup['customerUser'])
        ->get(route('customer.requests.show', $setup['requestA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.whatsapp_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96890000000?text='))
        );

    $setup['assignmentA']->update(['whatsapp_phone' => '01012345678']);

    $this->actingAs($setup['customerUser'])
        ->get(route('customer.requests.show', $setup['requestA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.whatsapp_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96890000000?text='))
        );

    expect($setup['owner']->fresh()->phone)->toBe('999000111')
        ->and($setup['customer']->phone)->toBe('0100555666');

    $setup['offerA']->update(['status' => OfferStatus::Withdrawn, 'withdrawn_at' => now()]);
    $this->actingAs($setup['customerUser'])
        ->get(route('customer.requests.show', $setup['requestA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));

    $setup['offerA']->update(['status' => OfferStatus::Invalidated, 'withdrawn_at' => null]);
    $this->actingAs($setup['customerUser'])
        ->get(route('customer.requests.show', $setup['requestA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));

    $stranger = User::factory()->create();
    Customer::factory()->create(['user_id' => $stranger->id]);
    $this->actingAs($stranger)
        ->get(route('customer.requests.show', $setup['requestA']))
        ->assertNotFound();
});

test('admin can update activity whatsapp through merchant category management', function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $merchant = Merchant::factory()->create();
    $assignment = MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => Category::factory()->create()->id,
        'whatsapp_phone' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('merchants.categories.index', $merchant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantCategoriesPage', false)
            ->where('assignments.data.0.whatsapp_phone', null)
        );

    $originalId = $assignment->id;
    $originalCategoryId = $assignment->category_id;

    $this->actingAs($admin)
        ->patch(route('merchants.categories.update', [$merchant, $assignment]), [
            'whatsapp_phone' => '96893333333',
            'category_id' => Category::factory()->create()->public_id,
            'merchant_id' => 999999,
        ])
        ->assertSessionHasErrors(['category_id', 'merchant_id']);

    $this->actingAs($admin)
        ->patch(route('merchants.categories.update', [$merchant, $assignment]), [
            'whatsapp_phone' => '96893333333',
        ])
        ->assertRedirect();

    expect($assignment->fresh()->whatsapp_phone)->toBe('96893333333')
        ->and($assignment->fresh()->id)->toBe($originalId)
        ->and($assignment->fresh()->category_id)->toBe($originalCategoryId);
});
