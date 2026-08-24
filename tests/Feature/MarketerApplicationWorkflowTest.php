<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\Marketers\Status as MarketerStatus;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Services\ReferralAttributionService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function marketerAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function referredCapabilities(User $referred): void
{
    Customer::factory()->create([
        'user_id' => $referred->id,
        'status' => CustomerStatus::Active,
    ]);
}

test('an authenticated user can apply and stays pending with an unused referral code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('marketer.application.store'), [
            'status' => MarketerStatus::Active->value,
            'user_id' => $user->id + 9,
            'marketer_id' => 99,
        ])
        ->assertSessionHasErrors(['status', 'user_id', 'marketer_id']);

    $this->actingAs($user)
        ->post(route('marketer.application.store'))
        ->assertRedirect(route('marketer.application.status'));

    $marketer = Marketer::query()->where('user_id', $user->id)->first();
    expect($marketer)->not->toBeNull()
        ->and($marketer->status)->toBe(MarketerStatus::Pending)
        ->and($marketer->referral_code)->not->toBeEmpty();

    $this->flushSession();
    $this->get('/?ref='.$marketer->referral_code)->assertOk();
    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBeNull();

    $this->actingAs($user)
        ->post(route('marketer.application.store'))
        ->assertRedirect(route('marketer.application.status'));

    expect(Marketer::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('customer and merchant users apply on the same user row', function () {
    app(MerchantPermissionService::class)->seedCatalog();
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($user)->post(route('marketer.application.store'))->assertRedirect();

    expect(User::query()->count())->toBe(1)
        ->and($user->fresh()->customer)->not->toBeNull()
        ->and($user->fresh()->merchantMemberships)->toHaveCount(1)
        ->and($user->fresh()->marketer?->status)->toBe(MarketerStatus::Pending);
});

test('pending rejected and inactive users cannot open the marketer portal', function () {
    foreach ([MarketerStatus::Pending, MarketerStatus::Rejected, MarketerStatus::Inactive] as $status) {
        $user = User::factory()->create();
        Marketer::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        $this->actingAs($user)
            ->get(route('marketer.home'))
            ->assertRedirect();
    }
});

test('admin approval activates the code and rejection then reapply reuse the same row', function () {
    $admin = marketerAdmin();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('marketer.application.store'));
    $marketer = Marketer::query()->where('user_id', $user->id)->first();
    $code = $marketer->referral_code;

    $this->actingAs($admin)
        ->get(route('marketers.index', ['status' => MarketerStatus::Pending->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketers/MarketersPage', false)
            ->where('pendingCount', 1));

    $this->actingAs($user)->get(route('marketer.home'))->assertRedirect();

    $this->actingAs($admin)->post(route('marketers.approve', $marketer))->assertRedirect();
    expect($marketer->fresh()->status)->toBe(MarketerStatus::Active);

    $this->actingAs($user->fresh())->get(route('marketer.home'))->assertOk();

    $this->actingAs($admin)->post(route('marketers.deactivate', $marketer->fresh()))->assertRedirect();
    expect($marketer->fresh()->status)->toBe(MarketerStatus::Inactive);
    $this->actingAs($user->fresh())->get(route('marketer.home'))->assertRedirect();

    $this->actingAs($admin)->post(route('marketers.reactivate', $marketer->fresh()))->assertRedirect();
    expect($marketer->fresh()->status)->toBe(MarketerStatus::Active);
    $this->actingAs($user->fresh())->get(route('marketer.home'))->assertOk();

    $pending = Marketer::factory()->pending()->create();
    $this->actingAs($admin)->post(route('marketers.reject', $pending))->assertRedirect();
    expect($pending->fresh()->status)->toBe(MarketerStatus::Rejected);

    $this->actingAs($pending->user)
        ->post(route('marketer.application.reapply'))
        ->assertRedirect(route('marketer.application.status'));

    expect(Marketer::query()->where('user_id', $pending->user_id)->count())->toBe(1)
        ->and($pending->fresh()->status)->toBe(MarketerStatus::Pending)
        ->and($pending->fresh()->referral_code)->toBe($pending->referral_code);
});

test('inactive historical referrals remain after deactivation', function () {
    $marketer = Marketer::factory()->create();
    $referred = User::factory()->create();
    MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $referred->id,
        'referral_code' => $marketer->referral_code,
        'landing_path' => '/',
        'registered_at' => now(),
    ]);

    $admin = marketerAdmin();
    $this->actingAs($admin)->post(route('marketers.deactivate', $marketer))->assertRedirect();

    expect(MarketerReferral::query()->count())->toBe(1)
        ->and($marketer->fresh()->status)->toBe(MarketerStatus::Inactive);
});

test('portal metrics count customer merchant and dual referrals without mixing other marketers', function () {
    app(MerchantPermissionService::class)->seedCatalog();
    $marketer = Marketer::factory()->create();
    $other = Marketer::factory()->create();

    $customerOnly = User::factory()->create(['name' => 'Cust Only', 'email' => 'cust-only@example.test']);
    Customer::factory()->create(['user_id' => $customerOnly->id, 'status' => CustomerStatus::Active]);

    $merchantOnly = User::factory()->create(['name' => 'Merch Only', 'email' => 'merch-only@example.test']);
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $merchantOnly->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    $dual = User::factory()->create(['name' => 'Dual User', 'email' => 'dual-ref@example.test']);
    Customer::factory()->create(['user_id' => $dual->id, 'status' => CustomerStatus::Active]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $dual->id,
        'merchant_id' => Merchant::factory()->create()->id,
        'status' => MembershipStatus::Active,
    ]);

    $plain = User::factory()->create();
    $foreign = User::factory()->create();

    foreach ([$customerOnly, $merchantOnly, $dual, $plain] as $referred) {
        MarketerReferral::query()->create([
            'marketer_id' => $marketer->id,
            'referred_user_id' => $referred->id,
            'referral_code' => $marketer->referral_code,
            'landing_path' => '/',
            'registered_at' => now(),
        ]);
    }

    MarketerReferral::query()->create([
        'marketer_id' => $other->id,
        'referred_user_id' => $foreign->id,
        'referral_code' => $other->referral_code,
        'landing_path' => '/',
        'registered_at' => now(),
    ]);

    $this->actingAs($marketer->user)
        ->get(route('marketer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketerPortal/HomePage', false)
            ->where('metrics.total_referred_users', 4)
            ->where('metrics.customers', 2)
            ->where('metrics.merchants', 2)
            ->where('metrics.dual', 1)
            ->where('metrics.registrations_this_month', 4));

    $this->actingAs($marketer->user)
        ->get(route('marketer.referrals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketerPortal/ReferralsPage', false)
            ->has('referrals.data', 4)
            ->missing('referrals.data.0.referred_user_id'));
});

test('marketer-only login lands on the marketer portal and public home uses that target', function () {
    $marketer = Marketer::factory()->create();

    $this->post('/login', [
        'email' => $marketer->user->email,
        'password' => 'password',
    ])->assertRedirect(route('marketer.home', absolute: false));

    $this->actingAs($marketer->user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('marketer.home', absolute: false))
            ->where('auth.capabilities.hasActiveMarketer', true));
});

test('customer plus marketer keeps customer login target and one user', function () {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id, 'status' => CustomerStatus::Active]);
    Marketer::factory()->create(['user_id' => $user->id]);

    expect(User::query()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.home', route('customer.home', absolute: false))
            ->where('auth.capabilities.hasActiveCustomer', true)
            ->where('auth.capabilities.hasActiveMarketer', true));
});

test('admin can attach an existing user as an active marketer without creating another user', function () {
    $admin = marketerAdmin();
    $user = User::factory()->create(['email' => 'attach-me@example.test']);
    $users = User::query()->count();

    $this->actingAs($admin)
        ->post(route('marketers.store'), [
            'mode' => 'attach',
            'status' => MarketerStatus::Active->value,
            'user_email' => 'attach-me@example.test',
        ])
        ->assertRedirect();

    expect(User::query()->count())->toBe($users)
        ->and($user->fresh()->marketer?->status)->toBe(MarketerStatus::Active);
});

test('admin create does not merge an existing email into a second user', function () {
    $admin = marketerAdmin();
    User::factory()->create(['email' => 'taken-marketer@example.test']);

    $this->actingAs($admin)
        ->post(route('marketers.store'), [
            'mode' => 'create',
            'status' => MarketerStatus::Active->value,
            'name' => 'New Marketer',
            'email' => 'taken-marketer@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'taken-marketer@example.test')->count())->toBe(1)
        ->and(Marketer::query()->count())->toBe(0);
});

test('non admin cannot approve a marketer', function () {
    $user = User::factory()->create();
    $marketer = Marketer::factory()->pending()->create();

    $this->actingAs($user)
        ->post(route('marketers.approve', $marketer))
        ->assertRedirect();

    expect($marketer->fresh()->status)->toBe(MarketerStatus::Pending);
});
