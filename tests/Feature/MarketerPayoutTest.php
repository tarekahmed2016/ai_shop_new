<?php

use App\Enums\MarketerCommissions\Status as CommissionStatus;
use App\Enums\Payments\Method;
use App\Models\Marketer;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\MarketerReferral;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\MarketerCommissionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->payoutAdmin = User::factory()->create();
    $this->payoutAdmin->assignRole('admin');
});

function payoutMarketerWithApproved(string $approved = '10.000'): Marketer
{
    $marketer = Marketer::factory()->create();
    $user = User::factory()->create();
    $referral = MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $user->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);
    $payment = PaymentTransaction::factory()->create(['payer_user_id' => $user->id]);
    MarketerCommission::factory()->create([
        'marketer_id' => $marketer->id,
        'marketer_referral_id' => $referral->id,
        'payment_transaction_id' => $payment->id,
        'referred_user_id' => $user->id,
        'commission_amount' => $approved,
        'status' => CommissionStatus::Approved,
    ]);

    return $marketer->fresh();
}

test('partial payouts reduce outstanding until zero', function () {
    $marketer = payoutMarketerWithApproved('10.000');
    $service = app(MarketerCommissionService::class);

    $service->recordPayout($marketer, '4.000', Method::BankTransfer, $this->payoutAdmin, 'ref-1', 'admin note');
    $afterFirst = $service->financialSummary($marketer);
    expect($afterFirst['paid'])->toBe('4.000')
        ->and($afterFirst['outstanding'])->toBe('6.000');

    $service->recordPayout($marketer, '6.000', Method::Cash, $this->payoutAdmin, null, null);
    $afterSecond = $service->financialSummary($marketer);
    expect($afterSecond['paid'])->toBe('10.000')
        ->and($afterSecond['outstanding'])->toBe('0.000');
});

test('payout larger than remaining outstanding is rejected', function () {
    $marketer = payoutMarketerWithApproved('10.000');
    $service = app(MarketerCommissionService::class);
    $service->recordPayout($marketer, '4.000', Method::Cash, $this->payoutAdmin, null, null);

    expect(fn () => $service->recordPayout($marketer, '7.000', Method::Cash, $this->payoutAdmin, null, null))
        ->toThrow(ValidationException::class);

    expect($service->financialSummary($marketer)['outstanding'])->toBe('6.000')
        ->and(MarketerPayout::query()->count())->toBe(1);
});

test('concurrent payout cannot overpay remaining entitlement', function () {
    $marketer = payoutMarketerWithApproved('10.000');
    $service = app(MarketerCommissionService::class);
    $service->recordPayout($marketer, '10.000', Method::BankTransfer, $this->payoutAdmin, null, null);

    expect(fn () => $service->recordPayout($marketer, '0.001', Method::Cash, $this->payoutAdmin, null, null))
        ->toThrow(ValidationException::class);

    expect(bcadd((string) MarketerPayout::query()->sum('amount'), '0', 3))->toBe('10.000');
    expect(file_get_contents(app_path('Services/MarketerCommissionService.php')))->toContain('lockForUpdate');
});

test('unauthorized users cannot record payouts', function () {
    $marketer = payoutMarketerWithApproved('10.000');
    $plain = User::factory()->create();

    $this->actingAs($plain)
        ->post(route('marketers.payouts.store', $marketer), [
            'amount' => '1.000',
            'payment_method' => Method::Cash->value,
            'paid_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('login'));

    expect(MarketerPayout::query()->count())->toBe(0);
});

test('marketer cannot payout themselves', function () {
    $marketer = payoutMarketerWithApproved('10.000');

    $this->actingAs($marketer->user)
        ->post(route('marketers.payouts.store', $marketer), [
            'amount' => '1.000',
            'payment_method' => Method::Cash->value,
            'paid_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('login'));

    expect(MarketerPayout::query()->count())->toBe(0);
});

test('there is no payout delete route', function () {
    expect(Route::has('marketers.payouts.destroy'))->toBeFalse()
        ->and(Route::has('marketer.payouts.destroy'))->toBeFalse();
});

test('marketer payout page hides admin notes and references', function () {
    $marketer = payoutMarketerWithApproved('10.000');
    app(MarketerCommissionService::class)->recordPayout(
        $marketer,
        '4.000',
        Method::BankTransfer,
        $this->payoutAdmin,
        'BANK-SECRET',
        'internal payout note',
    );

    $this->actingAs($marketer->user)
        ->get(route('marketer.payouts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketerPortal/PayoutsPage', false)
            ->has('payouts.data', 1)
            ->where('payouts.data.0.amount', '4.000')
            ->missing('payouts.data.0.reference')
            ->missing('payouts.data.0.notes'));
});

test('admin can record a payout from the marketer details page', function () {
    $marketer = payoutMarketerWithApproved('10.000');

    $this->actingAs($this->payoutAdmin)
        ->post(route('marketers.payouts.store', $marketer), [
            'amount' => '4.000',
            'payment_method' => Method::BankTransfer->value,
            'reference' => 'TRX-1',
            'notes' => 'first installment',
            'paid_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    expect(MarketerPayout::query()->count())->toBe(1)
        ->and(app(MarketerCommissionService::class)->financialSummary($marketer)['outstanding'])->toBe('6.000');
});
