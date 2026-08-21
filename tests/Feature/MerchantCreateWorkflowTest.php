<?php

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
use App\Services\MerchantCategoryService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->categoryA = Category::factory()->create(['name_en' => 'Activity A']);
    $this->categoryB = Category::factory()->create(['name_en' => 'Activity B']);
});

function merchantCreatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Shop',
        'phone' => '0123456789',
        'email' => 'shop@example.com',
        'status' => MerchantStatus::Active->value,
        'category_ids' => [test()->categoryA->public_id, test()->categoryB->public_id],
        'owner_name' => 'Shop Owner',
        'owner_email' => 'owner@example.com',
        'owner_phone' => '0100000000',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ], $overrides);
}

test('admin creates merchant with owner membership and categories', function () {
    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload())
        ->assertRedirect();

    $merchant = Merchant::query()->where('name', 'New Shop')->first();
    $owner = User::query()->where('email', 'owner@example.com')->first();
    $membership = MerchantUser::query()
        ->where('merchant_id', $merchant->id)
        ->where('user_id', $owner->id)
        ->first();

    expect($merchant)->not->toBeNull()
        ->and($owner)->not->toBeNull()
        ->and($owner->status)->toBe(UserStatus::Active)
        ->and($membership)->not->toBeNull()
        ->and($membership->role)->toBe(Role::Owner)
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($merchant->categories()->count())->toBe(2)
        ->and($merchant->categories()->pluck('categories.id')->sort()->values()->all())
        ->toEqual(collect([$this->categoryA->id, $this->categoryB->id])->sort()->values()->all())
        ->and(Hash::check('password12', $owner->getAuthPassword()))->toBeTrue()
        ->and($owner->getAuthPassword())->not->toBe('password12')
        ->and($owner->hasRole('admin'))->toBeFalse();
});

test('duplicate and invalid category ids are rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload([
            'category_ids' => [$this->categoryA->public_id, $this->categoryA->public_id],
        ]))
        ->assertSessionHasErrors('category_ids.1');

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload([
            'category_ids' => ['01INVALIDCATEGORYPUBLICID00'],
            'owner_email' => 'other-owner@example.com',
        ]))
        ->assertSessionHasErrors('category_ids.0');

    expect(Merchant::query()->where('name', 'New Shop')->exists())->toBeFalse();
});

test('inactive category cannot be assigned during merchant create', function () {
    $inactive = Category::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload([
            'category_ids' => [$inactive->public_id],
        ]))
        ->assertSessionHasErrors('category_ids.0');

    expect(Merchant::query()->where('name', 'New Shop')->exists())->toBeFalse();
});

test('password confirmation mismatch is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload([
            'password_confirmation' => 'different12',
        ]))
        ->assertSessionHasErrors('password');

    expect(Merchant::query()->where('name', 'New Shop')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'owner@example.com')->exists())->toBeFalse();
});

test('existing owner email is rejected and password is not overwritten', function () {
    $existing = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => 'original-secret',
    ]);

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload())
        ->assertSessionHasErrors('owner_email');

    expect(Merchant::query()->where('name', 'New Shop')->exists())->toBeFalse()
        ->and(Hash::check('original-secret', $existing->fresh()->getAuthPassword()))->toBeTrue()
        ->and(User::query()->where('email', 'owner@example.com')->count())->toBe(1);
});

test('transaction rolls back if user creation fails', function () {
    User::creating(function () {
        throw new RuntimeException('owner create failed');
    });

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload())
        ->assertServerError();

    expect(Merchant::query()->where('name', 'New Shop')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'owner@example.com')->exists())->toBeFalse()
        ->and(MerchantCategory::query()->count())->toBe(0)
        ->and(MerchantUser::query()->count())->toBe(0);
});

test('transaction rolls back if category attachment fails', function () {
    $this->mock(MerchantCategoryService::class, function ($mock) {
        $mock->shouldReceive('attach')->andThrow(new RuntimeException('category attach failed'));
    });

    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload())
        ->assertServerError();

    expect(Merchant::query()->where('name', 'New Shop')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'owner@example.com')->exists())->toBeFalse()
        ->and(MerchantUser::query()->count())->toBe(0);
});

test('activity logs do not contain password values', function () {
    $this->actingAs($this->admin)
        ->post(route('merchants.store'), merchantCreatePayload())
        ->assertRedirect();

    $logs = ActivityLog::query()->get();

    expect($logs)->not->toBeEmpty();

    foreach ($logs as $log) {
        $encoded = json_encode([
            $log->old_values,
            $log->new_values,
            $log->metadata,
            $log->subject_label,
        ]);

        expect($encoded)->not->toContain('password12')
            ->and($encoded)->not->toContain('"password"');
    }
});

test('existing merchant edit still works without owner fields', function () {
    $merchant = Merchant::factory()->create(['name' => 'Before']);

    $this->actingAs($this->admin)
        ->put(route('merchants.update', $merchant), [
            'name' => 'After',
            'phone' => $merchant->phone,
            'email' => $merchant->email,
            'status' => MerchantStatus::Inactive->value,
            'password' => 'should-not-apply',
            'owner_email' => 'ignored@example.com',
            'category_ids' => [$this->categoryA->public_id],
        ])
        ->assertRedirect();

    expect($merchant->fresh()->name)->toBe('After')
        ->and($merchant->fresh()->status)->toBe(MerchantStatus::Inactive)
        ->and($merchant->categories()->count())->toBe(0)
        ->and(User::query()->where('email', 'ignored@example.com')->exists())->toBeFalse();
});
