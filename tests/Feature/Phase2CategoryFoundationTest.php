<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\User;
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

test('platform admin can create a category with unique public_id', function () {
    $this->actingAs($this->admin)
        ->post(route('categories.store'), [
            'name_ar' => 'إلكترونيات',
            'name_en' => 'Electronics',
            'status' => CategoryStatus::Active->value,
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $category = Category::query()->where('name_en', 'Electronics')->first();

    expect($category)->not->toBeNull()
        ->and($category->public_id)->not->toBeEmpty()
        ->and(Str::isUlid($category->public_id))->toBeTrue()
        ->and(ActivityLog::where('event', Event::Created)->where('subject_id', $category->id)->exists())->toBeTrue();

    $second = Category::factory()->create();

    expect($second->public_id)->not->toBe($category->public_id);
});

test('platform admin can update a category including inactive status', function () {
    $category = Category::factory()->create(['name_en' => 'Before']);

    $this->actingAs($this->admin)
        ->put(route('categories.update', $category), [
            'name_ar' => $category->name_ar,
            'name_en' => 'After',
            'status' => CategoryStatus::Inactive->value,
            'sort_order' => 3,
        ])
        ->assertRedirect();

    expect($category->fresh()->name_en)->toBe('After')
        ->and($category->fresh()->status)->toBe(CategoryStatus::Inactive)
        ->and($category->fresh()->sort_order)->toBe(3);
});

test('category parent relationship works and cannot be its own parent', function () {
    $parent = Category::factory()->create(['name_en' => 'Parent']);
    $child = Category::factory()->create(['name_en' => 'Child']);

    $this->actingAs($this->admin)
        ->put(route('categories.update', $child), [
            'name_ar' => $child->name_ar,
            'name_en' => $child->name_en,
            'parent_id' => $parent->public_id,
            'status' => CategoryStatus::Active->value,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect($child->fresh()->parent_id)->toBe($parent->id);

    $this->actingAs($this->admin)
        ->put(route('categories.update', $child), [
            'name_ar' => $child->name_ar,
            'name_en' => $child->name_en,
            'parent_id' => $child->public_id,
            'status' => CategoryStatus::Active->value,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($this->admin)
        ->put(route('categories.update', $parent), [
            'name_ar' => $parent->name_ar,
            'name_en' => $parent->name_en,
            'parent_id' => $child->public_id,
            'status' => CategoryStatus::Active->value,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

test('unauthorized user cannot manage categories', function () {
    $this->actingAs($this->user)
        ->get(route('categories.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->post(route('categories.store'), [
            'name_ar' => 'ممنوع',
            'name_en' => 'Blocked',
            'status' => CategoryStatus::Active->value,
        ])
        ->assertRedirect(route('login'));
});

test('category can be attached to merchant A', function () {
    $merchant = Merchant::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.categories.store', $merchant), [
            'category_id' => $category->public_id,
            'merchant_id' => 999999,
        ])
        ->assertRedirect();

    expect(MerchantCategory::query()
        ->where('merchant_id', $merchant->id)
        ->where('category_id', $category->id)
        ->exists())->toBeTrue()
        ->and(ActivityLog::where('event', Event::Created)
            ->where('subject_type', MerchantCategory::class)
            ->exists())->toBeTrue();
});

test('duplicate merchant category assignment is rejected', function () {
    $merchant = Merchant::factory()->create();
    $category = Category::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('merchants.categories.store', $merchant), [
            'category_id' => $category->public_id,
        ])
        ->assertSessionHasErrors('category_id');

    expect(MerchantCategory::query()
        ->where('merchant_id', $merchant->id)
        ->where('category_id', $category->id)
        ->count())->toBe(1);
});

test('same category can belong to merchant A and merchant B', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.categories.store', $merchantA), [
            'category_id' => $category->public_id,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('merchants.categories.store', $merchantB), [
            'category_id' => $category->public_id,
        ])
        ->assertRedirect();

    expect($category->merchants()->count())->toBe(2);
});

test('merchant A cannot modify merchant B category assignments', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $category = Category::factory()->create();
    $assignment = MerchantCategory::factory()->create([
        'merchant_id' => $merchantB->id,
        'category_id' => $category->id,
    ]);

    MerchantUser::factory()->create([
        'user_id' => $this->user->id,
        'merchant_id' => $merchantA->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($this->user)
        ->post(route('merchants.categories.store', $merchantB), [
            'category_id' => $category->public_id,
        ])
        ->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->delete(route('merchants.categories.destroy', [
            'merchant' => $merchantB,
            'merchantCategory' => $assignment,
        ]))
        ->assertRedirect(route('login'));

    expect(MerchantCategory::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

test('forged merchant public_id and assignment id have no effect', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $category = Category::factory()->create();
    $assignmentB = MerchantCategory::factory()->create([
        'merchant_id' => $merchantB->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('merchants.categories.store', ['merchant' => '01FAKEPUBLICIDNOTFOUND00']), [
            'category_id' => $category->public_id,
        ])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(route('merchants.categories.destroy', [
            'merchant' => $merchantA,
            'merchantCategory' => $assignmentB,
        ]))
        ->assertNotFound();

    expect(MerchantCategory::query()->whereKey($assignmentB->id)->exists())->toBeTrue()
        ->and($merchantA->categories()->count())->toBe(0);
});

test('inactive category cannot be attached', function () {
    $merchant = Merchant::factory()->create();
    $category = Category::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.categories.store', $merchant), [
            'category_id' => $category->public_id,
        ])
        ->assertSessionHasErrors('category_id');

    expect($merchant->categories()->count())->toBe(0);
});

test('removed category assignment disappears correctly', function () {
    $merchant = Merchant::factory()->create();
    $category = Category::factory()->create();
    $assignment = MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('merchants.categories.destroy', [
            'merchant' => $merchant,
            'merchantCategory' => $assignment,
        ]))
        ->assertRedirect();

    expect(MerchantCategory::query()->whereKey($assignment->id)->exists())->toBeFalse()
        ->and(ActivityLog::where('event', Event::Deleted)
            ->where('subject_type', MerchantCategory::class)
            ->exists())->toBeTrue();
});

test('inactive merchant membership still cannot manage platform categories', function () {
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $category = Category::factory()->create();

    MerchantUser::factory()->create([
        'user_id' => $this->user->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Inactive,
    ]);

    $this->actingAs($this->user)
        ->post(route('merchants.categories.store', $merchant), [
            'category_id' => $category->public_id,
        ])
        ->assertRedirect(route('login'));
});

test('categories index is available to platform admin', function () {
    $this->actingAs($this->admin)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Categories/CategoriesPage', false));
});

test('merchant category options include parent_id for hierarchy ui', function () {
    $parent = Category::factory()->create(['name_en' => 'Parent Cat']);
    $child = Category::factory()->create([
        'name_en' => 'Child Cat',
        'parent_id' => $parent->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableCategories')
            ->where('availableCategories', function ($categories) use ($parent, $child) {
                $items = collect($categories);
                $parentRow = $items->firstWhere('id', $parent->id);
                $childRow = $items->firstWhere('id', $child->id);

                return $parentRow !== null
                    && $childRow !== null
                    && $parentRow['parent_id'] === null
                    && (int) $childRow['parent_id'] === $parent->id;
            })
        );
});
