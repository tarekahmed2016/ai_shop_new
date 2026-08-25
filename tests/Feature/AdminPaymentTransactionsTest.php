<?php

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\Payments\Method;
use App\Enums\Payments\Status as PaymentStatus;
use App\Enums\Payments\Type as PaymentType;
use App\Models\Customer;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->payAdmin = User::factory()->create();
    $this->payAdmin->assignRole('admin');
});

test('admin can list filter and open payment audit details', function () {
    $payerA = User::factory()->create(['name' => 'Payer Alpha', 'email' => 'alpha-pay@example.test']);
    $payerB = User::factory()->create(['name' => 'Payer Beta', 'email' => 'beta-pay@example.test']);
    $customerA = Customer::factory()->create(['user_id' => $payerA->id]);
    $customerB = Customer::factory()->create(['user_id' => $payerB->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customerA,
        5,
        ExtraSource::BankTransfer,
        'REF-A',
        'Note A',
        $this->payAdmin,
        paidAmount: '2.000',
    );
    app(CustomerExtraRequestService::class)->addCredits(
        $customerB,
        3,
        ExtraSource::Cash,
        'REF-B',
        'Note B',
        $this->payAdmin,
        paidAmount: '4.000',
    );

    $this->actingAs($this->payAdmin)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/PaymentsPage', false)
            ->has('payments.data', 2));

    $this->actingAs($this->payAdmin)
        ->get(route('payments.index', ['type' => PaymentType::CustomerExtraRequests->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2));

    $this->actingAs($this->payAdmin)
        ->get(route('payments.index', ['method' => Method::Cash->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.payer.email', 'beta-pay@example.test'));

    $this->actingAs($this->payAdmin)
        ->get(route('payments.index', ['payer' => 'alpha-pay@example.test']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.payer.email', 'alpha-pay@example.test'));

    $this->actingAs($this->payAdmin)
        ->get(route('payments.index', ['status' => PaymentStatus::Paid->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2));

    $today = now()->toDateString();
    $this->actingAs($this->payAdmin)
        ->get(route('payments.index', ['date_from' => $today, 'date_to' => $today]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2));

    $payment = PaymentTransaction::query()->where('payer_user_id', $payerA->id)->first();

    $this->actingAs($this->payAdmin)
        ->get(route('payments.show', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/PaymentShowPage', false)
            ->where('payment.public_id', $payment->public_id)
            ->where('payment.reference', 'REF-A')
            ->where('payment.notes', 'Note A')
            ->has('payment.extra_request_ledger', 1)
            ->where('payment.extra_request_ledger.0.amount', 5)
            ->has('payment.merchant_credit_ledger', 0));
});

test('payment transactions have no update or delete routes and unauthorized users are blocked', function () {
    expect(Route::has('payments.update'))->toBeFalse()
        ->and(Route::has('payments.destroy'))->toBeFalse()
        ->and(Route::has('payments.store'))->toBeFalse();

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        2,
        ExtraSource::Cash,
        null,
        null,
        $this->payAdmin,
        paidAmount: '1.000',
    );
    $payment = PaymentTransaction::query()->first();

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('payments.show', $payment))
        ->assertRedirect(route('login'));

    expect(fn () => $payment->update(['amount' => '9.000']))
        ->toThrow(LogicException::class)
        ->and(fn () => $payment->delete())
        ->toThrow(LogicException::class)
        ->and(bcadd((string) $payment->fresh()->amount, '0', 3))->toBe('1.000');
});
