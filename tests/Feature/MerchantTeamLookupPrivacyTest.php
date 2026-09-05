<?php

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    RateLimiter::clear('merchant-team-write');
});

function privacyMembership(User $user, Merchant $merchant, Role $role = Role::Owner): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => MembershipStatus::Active,
    ]);
}

function privacyOwner(): array
{
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $owner = User::factory()->create();
    privacyMembership($owner, $merchant, Role::Owner);

    return compact('merchant', 'owner');
}

function privacyTeamPayload(string $email, array $overrides = []): array
{
    return array_merge([
        'email' => $email,
        'name' => 'Team Person',
        'phone' => '0111111111',
        'password' => 'password12',
        'password_confirmation' => 'password12',
        'role' => Role::Staff->value,
        'status' => MembershipStatus::Active->value,
    ], $overrides);
}

test('merchant team lookup route is removed', function () {
    expect(Route::has('merchant.team.lookup'))->toBeFalse();
});

test('existing and unknown emails produce the same add-member HTTP outcome', function () {
    ['merchant' => $merchant, 'owner' => $owner] = privacyOwner();
    $existing = User::factory()->create([
        'email' => 'known.member@example.com',
        'name' => 'Secret Name',
        'phone' => '0188888888',
    ]);

    $existingResponse = $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('known.member@example.com'));

    $unknownResponse = $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('unknown.member@example.com'));

    $existingResponse->assertRedirect()->assertSessionHas('success', 'تم الإضافة بنجاح');
    $unknownResponse->assertRedirect()->assertSessionHas('success', 'تم الإضافة بنجاح');

    expect($existingResponse->status())->toBe($unknownResponse->status())
        ->and($existingResponse->headers->get('Location'))->toBe($unknownResponse->headers->get('Location'))
        ->and($existingResponse->getContent())->not->toContain((string) $existing->id)
        ->and($existingResponse->getContent())->not->toContain('Secret Name')
        ->and($existingResponse->getContent())->not->toContain('0188888888')
        ->and($unknownResponse->getContent())->not->toContain('unknown.member@example.com');
});

test('missing name or password fails the same way for existing and unknown emails', function () {
    ['merchant' => $merchant, 'owner' => $owner] = privacyOwner();
    User::factory()->create(['email' => 'already@example.com']);

    $existing = $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('already@example.com', [
            'name' => '',
            'password' => '',
            'password_confirmation' => '',
        ]));

    $unknown = $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('fresh@example.com', [
            'name' => '',
            'password' => '',
            'password_confirmation' => '',
        ]));

    $existing->assertSessionHasErrors(['name', 'password']);
    $unknown->assertSessionHasErrors(['name', 'password']);
});

test('staff cannot add members and cannot reach a user lookup', function () {
    $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    privacyMembership($owner, $merchant, Role::Owner);
    privacyMembership($staff, $merchant, Role::Staff);

    $this->actingAs($staff)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('blocked-lookup@example.com'))
        ->assertForbidden();

    $this->actingAs($staff)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->getJson('/merchant/team/lookup?email=blocked-lookup@example.com')
        ->assertStatus(405)
        ->assertJsonMissingPath('exists')
        ->assertJsonMissingPath('user');

    expect(MerchantUser::query()->whereHas('user', fn ($query) => $query->where('email', 'blocked-lookup@example.com'))->exists())->toBeFalse();
});

test('adding a user from another merchant does not leak their profile in the add response', function () {
    ['merchant' => $merchantA, 'owner' => $ownerA] = privacyOwner();
    ['merchant' => $merchantB] = privacyOwner();
    $foreign = User::factory()->create([
        'email' => 'foreign.owner@example.com',
        'name' => 'Foreign Secret',
        'phone' => '0177777777',
    ]);
    privacyMembership($foreign, $merchantB, Role::Staff);

    $response = $this->actingAs($ownerA)
        ->withSession([MerchantContextService::SESSION_KEY => $merchantA->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('Foreign.Owner@example.com', [
            'name' => 'Should Not Overwrite',
            'phone' => '0100000000',
        ]));

    $response->assertRedirect()->assertSessionHas('success', 'تم الإضافة بنجاح');
    expect($response->getContent())->not->toContain('Foreign Secret')
        ->and($response->getContent())->not->toContain('0177777777')
        ->and($response->getContent())->not->toContain((string) $foreign->id)
        ->and($foreign->fresh()->name)->toBe('Foreign Secret')
        ->and($foreign->fresh()->phone)->toBe('0177777777')
        ->and(MerchantUser::query()->where('merchant_id', $merchantA->id)->where('user_id', $foreign->id)->exists())->toBeTrue();
});

test('team add normalizes email case and attaches the existing account', function () {
    ['merchant' => $merchant, 'owner' => $owner] = privacyOwner();
    $existing = User::factory()->create(['email' => 'cased.user@example.com']);

    $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('Cased.User@Example.com'))
        ->assertRedirect();

    expect(User::query()->whereRaw('LOWER(email) = ?', ['cased.user@example.com'])->count())->toBe(1)
        ->and(MerchantUser::query()->where('merchant_id', $merchant->id)->where('user_id', $existing->id)->exists())->toBeTrue();
});

test('merchant team add is rate limited', function () {
    ['merchant' => $merchant, 'owner' => $owner] = privacyOwner();

    for ($i = 1; $i <= 10; $i++) {
        $this->actingAs($owner)
            ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
            ->post(route('merchant.team.store'), privacyTeamPayload("rate-{$i}@example.com"))
            ->assertRedirect();
    }

    $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->post(route('merchant.team.store'), privacyTeamPayload('rate-overflow@example.com'))
        ->assertStatus(429);
});
