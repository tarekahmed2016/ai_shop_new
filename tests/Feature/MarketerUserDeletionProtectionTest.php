<?php

use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerCommission;
use App\Models\MarketerReferral;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role as SpatieRole;

/*
|--------------------------------------------------------------------------
| Regression tests for: deleting a User with a Marketer profile silently
| destroying referral/commission audit history (cascadeOnDelete).
|--------------------------------------------------------------------------
|
| Fix has two layers, both covered here:
|   1. Database layer: marketers.user_id, marketer_referrals.marketer_id,
|      marketer_referrals.referred_user_id and marketer_commissions.marketer_id
|      were changed from ON DELETE CASCADE to ON DELETE RESTRICT.
|   2. Application layer: AdminGuardService::ensureCanDeleteUser() (called
|      from UserService::delete()) now refuses to delete a user who has a
|      Marketer, Customer, or merchant team membership profile.
*/

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

// --- Layer 1: database-level protection (defense in depth) ---------------

test('database refuses to delete a user while their marketer profile exists', function () {
    $marketer = Marketer::factory()->create();

    expect(fn () => $marketer->user->delete())->toThrow(QueryException::class);
    expect(User::find($marketer->user_id))->not->toBeNull();
    expect(Marketer::find($marketer->id))->not->toBeNull();
});

test('database refuses to delete a marketer while referral history exists', function () {
    $marketer = Marketer::factory()->create();
    $referred = User::factory()->create();

    MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $referred->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);

    expect(fn () => $marketer->delete())->toThrow(QueryException::class);
    expect(Marketer::find($marketer->id))->not->toBeNull();
    expect(MarketerReferral::query()->where('marketer_id', $marketer->id)->count())->toBe(1);
});

test('database refuses to delete a marketer while commission history exists', function () {
    $marketer = Marketer::factory()->create();
    $referral = MarketerReferral::factory()->create(['marketer_id' => $marketer->id]);
    $payment = PaymentTransaction::factory()->create(['payer_user_id' => $referral->referred_user_id]);

    MarketerCommission::factory()->create([
        'marketer_id' => $marketer->id,
        'marketer_referral_id' => $referral->id,
        'payment_transaction_id' => $payment->id,
        'referred_user_id' => $referral->referred_user_id,
    ]);

    expect(fn () => $marketer->delete())->toThrow(QueryException::class);
    expect(Marketer::find($marketer->id))->not->toBeNull();
    expect(MarketerCommission::query()->where('marketer_id', $marketer->id)->count())->toBe(1);
});

// --- Layer 2: application-level guard (UserService::delete / admin UI) ---

test('deleting a marketer user through the admin UI does not delete their commission history', function () {
    $marketer = Marketer::factory()->create();
    $referral = MarketerReferral::factory()->create(['marketer_id' => $marketer->id]);
    $payment = PaymentTransaction::factory()->create(['payer_user_id' => $referral->referred_user_id]);
    $commission = MarketerCommission::factory()->create([
        'marketer_id' => $marketer->id,
        'marketer_referral_id' => $referral->id,
        'payment_transaction_id' => $payment->id,
        'referred_user_id' => $referral->referred_user_id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $marketer->user))
        ->assertSessionHasErrors('user');

    expect(User::find($marketer->user_id))->not->toBeNull()
        ->and(Marketer::find($marketer->id))->not->toBeNull()
        ->and(MarketerCommission::find($commission->id))->not->toBeNull();
});

test('deleting a marketer user through the admin UI does not delete their referral history', function () {
    $marketer = Marketer::factory()->create();
    $referred = User::factory()->create();
    $referral = MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $referred->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $marketer->user))
        ->assertSessionHasErrors('user');

    expect(User::find($marketer->user_id))->not->toBeNull()
        ->and(MarketerReferral::find($referral->id))->not->toBeNull();
});

test('deleting a user with a customer profile is blocked', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $user))
        ->assertSessionHasErrors('user');

    expect(User::find($user->id))->not->toBeNull();
    expect(Customer::find($customer->id))->not->toBeNull();
});

test('deleting a user with a merchant team membership is blocked', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->create();
    $membership = MerchantUser::factory()->owner()->create([
        'user_id' => $owner->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $owner))
        ->assertSessionHasErrors('user');

    expect(User::find($owner->id))->not->toBeNull();
    expect(MerchantUser::find($membership->id))->not->toBeNull();
});

test('a plain user with no marketer, customer, or merchant profile can still be deleted normally', function () {
    $plainUser = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $plainUser))
        ->assertRedirect();

    expect(User::find($plainUser->id))->toBeNull();
});

test('an admin can still be deleted once their marketer profile is removed first, as long as another admin remains', function () {
    $secondAdmin = User::factory()->create();
    $secondAdmin->assignRole('admin');

    $marketer = Marketer::factory()->create();
    $marketer->user->assignRole('admin');

    // Still blocked while the marketer profile exists...
    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $marketer->user))
        ->assertSessionHasErrors('user');
    expect(User::find($marketer->user_id))->not->toBeNull();

    // ...but once the business relationship is explicitly removed, normal deletion resumes.
    $marketer->delete();

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $marketer->user))
        ->assertRedirect();
    expect(User::find($marketer->user_id))->toBeNull();
});
