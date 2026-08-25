<?php

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\MarketerCommissions\Status as CommissionStatus;
use App\Enums\MerchantOfferCredits\TransactionSource as CreditSource;
use App\Enums\Payments\Status as PaymentStatus;
use App\Enums\Payments\Type as PaymentType;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerCommission;
use App\Models\MarketerReferral;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingChange;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use App\Services\MarketerCommissionService;
use App\Services\MerchantOfferCreditService;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->refAdmin = User::factory()->create();
    $this->refAdmin->assignRole('admin');
});

function commissionMarketer(): Marketer
{
    return Marketer::factory()->create();
}

function commissionRefer(Marketer $marketer, User $user): void
{
    MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $user->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);
}

test('referred customer payment creates an approved commission at the customer rate', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        5,
        ExtraSource::BankTransfer,
        'secret-ref',
        'internal note',
        $this->refAdmin,
        paidAmount: '10.000',
    );

    $commission = MarketerCommission::query()->where('marketer_id', $marketer->id)->first();

    expect($commission)->not->toBeNull()
        ->and($commission->status)->toBe(CommissionStatus::Approved)
        ->and(bcadd((string) $commission->payment_amount, '0', 3))->toBe('10.000')
        ->and(bcadd((string) $commission->commission_rate, '0', 3))->toBe('10.000')
        ->and(bcadd((string) $commission->commission_amount, '0', 3))->toBe('1.000')
        ->and($commission->payment_type)->toBe(PaymentType::CustomerExtraRequests)
        ->and($commission->referred_user_id)->toBe($user->id);
});

test('unrelated payment creates no commission', function () {
    commissionMarketer();
    $stranger = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $stranger->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        5,
        ExtraSource::Cash,
        null,
        null,
        $this->refAdmin,
        paidAmount: '8.000',
    );

    expect(MarketerCommission::query()->count())->toBe(0);
});

test('merchant payment uses the merchant commission rate', function () {
    $marketer = commissionMarketer();
    $owner = User::factory()->create();
    commissionRefer($marketer, $owner);
    $merchant = Merchant::factory()->create();
    attachMerchantOwner($merchant, $owner);

    app(MerchantOfferCreditService::class)->addCredits(
        $merchant,
        20,
        CreditSource::BankTransfer,
        'mc-ref',
        'internal',
        creditAdmin(),
        paidAmount: '10.000',
    );

    $commission = MarketerCommission::query()->where('marketer_id', $marketer->id)->first();

    expect($commission)->not->toBeNull()
        ->and($commission->payment_type)->toBe(PaymentType::MerchantOfferCredits)
        ->and(bcadd((string) $commission->commission_rate, '0', 3))->toBe('20.000')
        ->and(bcadd((string) $commission->commission_amount, '0', 3))->toBe('2.000');
});

test('dual referred user follows payment type for commission rate', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $merchant = Merchant::factory()->create();
    attachMerchantOwner($merchant, $user);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        2,
        ExtraSource::Cash,
        null,
        null,
        $this->refAdmin,
        paidAmount: '4.000',
    );
    app(MerchantOfferCreditService::class)->addCredits(
        $merchant,
        10,
        CreditSource::Cash,
        null,
        null,
        creditAdmin(),
        paidAmount: '4.000',
    );

    $rows = MarketerCommission::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->payment_type)->toBe(PaymentType::CustomerExtraRequests)
        ->and(bcadd((string) $rows[0]->commission_amount, '0', 3))->toBe('0.400')
        ->and($rows[1]->payment_type)->toBe(PaymentType::MerchantOfferCredits)
        ->and(bcadd((string) $rows[1]->commission_amount, '0', 3))->toBe('0.800');
});

test('duplicate processing creates only one commission', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        3,
        ExtraSource::Cash,
        null,
        null,
        $this->refAdmin,
        paidAmount: '5.000',
    );

    $payment = PaymentTransaction::query()->first();
    $first = app(MarketerCommissionService::class)->createForPaidPayment($payment);
    $second = app(MarketerCommissionService::class)->createForPaidPayment($payment);

    expect(MarketerCommission::query()->count())->toBe(1)
        ->and($first->id)->toBe($second->id);
});

test('decimal commission calculation uses three places without floats', function () {
    $service = app(MarketerCommissionService::class);

    expect($service->calculateCommissionAmount('1.111', '10.000'))->toBe('0.111')
        ->and($service->calculateCommissionAmount('10.000', '20.000'))->toBe('2.000');
});

test('historical rate snapshot is preserved after a later rate change', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::Cash,
        null,
        null,
        $this->refAdmin,
        paidAmount: '10.000',
    );

    app(MarketerCommissionService::class)->setGlobalRates('50.000', '20.000', $this->refAdmin);

    $commission = MarketerCommission::query()->first();

    expect(bcadd((string) $commission->commission_rate, '0', 3))->toBe('10.000')
        ->and(bcadd((string) $commission->commission_amount, '0', 3))->toBe('1.000');
});

test('pending commissions do not count toward outstanding while approved do', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);
    $payment = PaymentTransaction::factory()->create(['payer_user_id' => $user->id]);

    MarketerCommission::factory()->create([
        'marketer_id' => $marketer->id,
        'marketer_referral_id' => MarketerReferral::query()->where('referred_user_id', $user->id)->value('id'),
        'payment_transaction_id' => $payment->id,
        'referred_user_id' => $user->id,
        'commission_amount' => '10.000',
        'status' => CommissionStatus::Approved,
    ]);

    $pendingPayment = PaymentTransaction::factory()->create(['payer_user_id' => $user->id]);
    MarketerCommission::factory()->pending()->create([
        'marketer_id' => $marketer->id,
        'marketer_referral_id' => MarketerReferral::query()->where('referred_user_id', $user->id)->value('id'),
        'payment_transaction_id' => $pendingPayment->id,
        'referred_user_id' => $user->id,
        'commission_amount' => '7.000',
        'status' => CommissionStatus::Pending,
    ]);

    $summary = app(MarketerCommissionService::class)->financialSummary($marketer);

    expect($summary['approved_commission'])->toBe('10.000')
        ->and($summary['pending_commission'])->toBe('7.000')
        ->and($summary['outstanding'])->toBe('10.000');
});

test('non-paid payment creates no commission', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);

    $payment = PaymentTransaction::factory()->create([
        'payer_user_id' => $user->id,
        'status' => PaymentStatus::Pending,
        'type' => PaymentType::CustomerExtraRequests,
        'amount' => '9.000',
    ]);

    $result = app(MarketerCommissionService::class)->createForPaidPayment($payment);

    expect($result)->toBeNull()
        ->and(MarketerCommission::query()->count())->toBe(0);
});

test('subscription payments are not commissioned', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);

    $payment = PaymentTransaction::factory()->create([
        'payer_user_id' => $user->id,
        'type' => PaymentType::Subscription,
        'status' => PaymentStatus::Paid,
        'amount' => '9.000',
    ]);

    expect(app(MarketerCommissionService::class)->createForPaidPayment($payment))->toBeNull()
        ->and(MarketerCommission::query()->count())->toBe(0);
});

test('per-marketer override is used instead of the global rate', function () {
    $marketer = commissionMarketer();
    $marketer->customer_commission_rate = '5.000';
    $marketer->save();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::Cash,
        null,
        null,
        $this->refAdmin,
        paidAmount: '10.000',
    );

    expect(bcadd((string) MarketerCommission::query()->value('commission_amount'), '0', 3))->toBe('0.500')
        ->and(bcadd((string) MarketerCommission::query()->value('commission_rate'), '0', 3))->toBe('5.000');
});

test('unchanged commission settings do not write an audit row', function () {
    $service = app(MarketerCommissionService::class);
    $service->setGlobalRates('10.000', '20.000', $this->refAdmin);
    $firstCount = PlatformSettingChange::query()
        ->whereIn('key', [
            PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER,
            PlatformSetting::KEY_MARKETER_COMMISSION_MERCHANT,
        ])
        ->count();

    $service->setGlobalRates('10.000', '20.000', $this->refAdmin);

    $secondCount = PlatformSettingChange::query()
        ->whereIn('key', [
            PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER,
            PlatformSetting::KEY_MARKETER_COMMISSION_MERCHANT,
        ])
        ->count();

    expect($secondCount)->toBe($firstCount);
});

test('changed commission settings write an audit row', function () {
    app(MarketerCommissionService::class)->setGlobalRates('12.500', '20.000', $this->refAdmin);

    $change = PlatformSettingChange::query()
        ->where('key', PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER)
        ->latest('id')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->new_value)->toBe('12.500')
        ->and($change->changed_by_user_id)->toBe($this->refAdmin->id);
});

test('backfill command is idempotent for existing paid referred payments', function () {
    $marketer = commissionMarketer();
    $user = User::factory()->create();
    commissionRefer($marketer, $user);

    $payment = PaymentTransaction::factory()->create([
        'payer_user_id' => $user->id,
        'type' => PaymentType::CustomerExtraRequests,
        'status' => PaymentStatus::Paid,
        'amount' => '6.000',
    ]);

    Artisan::call('marketers:backfill-commissions');
    Artisan::call('marketers:backfill-commissions');

    expect(MarketerCommission::query()->where('payment_transaction_id', $payment->id)->count())->toBe(1);
});

test('marketer commissions page shows own rows only and hides payment secrets', function () {
    $marketer = commissionMarketer();
    $other = commissionMarketer();
    $user = User::factory()->create(['name' => 'Referred Own']);
    $otherUser = User::factory()->create(['name' => 'Other Referred']);
    commissionRefer($marketer, $user);
    commissionRefer($other, $otherUser);
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $otherCustomer = Customer::factory()->create(['user_id' => $otherUser->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::BankTransfer,
        'SECRET-REF',
        'internal secret',
        $this->refAdmin,
        paidAmount: '3.000',
    );
    app(CustomerExtraRequestService::class)->addCredits(
        $otherCustomer,
        1,
        ExtraSource::Cash,
        'OTHER-REF',
        'other note',
        $this->refAdmin,
        paidAmount: '9.000',
    );

    $this->actingAs($marketer->user)
        ->get(route('marketer.commissions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketerPortal/CommissionsPage', false)
            ->has('commissions.data', 1)
            ->where('commissions.data.0.referred_user_name', 'Referred Own')
            ->missing('commissions.data.0.notes')
            ->missing('commissions.data.0.reference')
            ->where('summary.approved_commission', '0.300'));
});
