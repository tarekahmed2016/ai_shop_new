<?php

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerExtraRequests\TransactionType;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\Payments\Status as PaymentStatus;
use App\Enums\Payments\Type as PaymentType;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->payAdmin = User::factory()->create();
    $this->payAdmin->assignRole('admin');
});

function paidExtraCustomer(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    return compact('user', 'customer');
}

test('admin paid extra request add creates a central payment and linked ledger in one transaction', function () {
    ['user' => $user, 'customer' => $customer] = paidExtraCustomer();

    $this->actingAs($this->payAdmin)
        ->post(route('customers.extra-requests.store', $customer), [
            'amount' => 5,
            'source' => TransactionSource::BankTransfer->value,
            'paid_amount' => '2.000',
            'reference' => 'BANK-EXTRA-1',
            'notes' => 'Paid extra pack',
        ])
        ->assertRedirect(route('customers.extra-requests.index', $customer));

    $payment = PaymentTransaction::query()->first();
    $ledger = CustomerExtraRequestTransaction::query()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->payer_user_id)->toBe($user->id)
        ->and($payment->type)->toBe(PaymentType::CustomerExtraRequests)
        ->and(bcadd((string) $payment->amount, '0', 3))->toBe('2.000')
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->related_customer_id)->toBe($customer->id)
        ->and($ledger->amount)->toBe(5)
        ->and($ledger->payment_transaction_id)->toBe($payment->id)
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(5);
});

test('promo extra request add creates no payment transaction', function () {
    ['customer' => $customer] = paidExtraCustomer();

    $this->actingAs($this->payAdmin)
        ->post(route('customers.extra-requests.store', $customer), [
            'amount' => 5,
            'source' => TransactionSource::PromotionalBonus->value,
        ])
        ->assertRedirect();

    expect(PaymentTransaction::query()->count())->toBe(0)
        ->and(CustomerExtraRequestTransaction::query()->value('type'))->toBe(TransactionType::PromotionalBonus)
        ->and(CustomerExtraRequestTransaction::query()->value('payment_transaction_id'))->toBeNull()
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(5);
});

test('bulk extra request add creates a payment per customer when paid', function () {
    ['user' => $userA, 'customer' => $customerA] = paidExtraCustomer();
    ['user' => $userB, 'customer' => $customerB] = paidExtraCustomer();

    $this->actingAs($this->payAdmin)
        ->post(route('customers.extra-requests.bulk'), [
            'customer_public_ids' => [$customerA->public_id, $customerB->public_id],
            'amount' => 3,
            'source' => TransactionSource::Cash->value,
            'paid_amount' => '1.500',
        ])
        ->assertRedirect();

    expect(PaymentTransaction::query()->count())->toBe(2)
        ->and(PaymentTransaction::query()->pluck('payer_user_id')->sort()->values()->all())
        ->toBe(collect([$userA->id, $userB->id])->sort()->values()->all())
        ->and(CustomerExtraRequestTransaction::query()->whereNotNull('payment_transaction_id')->count())->toBe(2);
});

test('unauthorized users cannot add extra request credits', function () {
    ['user' => $user, 'customer' => $customer] = paidExtraCustomer();

    $this->actingAs($user)
        ->post(route('customers.extra-requests.store', $customer), [
            'amount' => 5,
            'source' => TransactionSource::Cash->value,
            'paid_amount' => '2.000',
        ])
        ->assertRedirect(route('login'));

    $this->actingAs($this->payAdmin)
        ->get(route('customers.extra-requests.index', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/CustomerExtraRequestsPage', false)
            ->where('balance', 0));
});
