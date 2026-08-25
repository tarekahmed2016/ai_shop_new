<?php

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Enums\Payments\Status as PaymentStatus;
use App\Enums\Payments\Type as PaymentType;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\PaymentTransaction;
use App\Services\MerchantOfferCreditService;

test('paid merchant offer credits create a central payment for the owner payer', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $owner = attachMerchantOwner($merchant);

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::BankTransfer->value,
            'paid_amount' => '5.000',
            'reference' => 'BANK-MC-1',
        ])
        ->assertRedirect();

    $ledger = MerchantOfferCreditTransaction::query()->first();
    $payment = PaymentTransaction::query()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->payer_user_id)->toBe($owner->id)
        ->and($payment->type)->toBe(PaymentType::MerchantOfferCredits)
        ->and(bcadd((string) $payment->amount, '0', 3))->toBe('5.000')
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->related_merchant_id)->toBe($merchant->id)
        ->and($ledger->payment_transaction_id)->toBe($payment->id)
        ->and(bcadd((string) $ledger->paid_amount, '0', 3))->toBe('5.000');
});

test('promo merchant credits and offer submit create no payment transaction', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    attachMerchantOwner($merchant);

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::PromotionalBonus->value,
        ])
        ->assertRedirect();

    enableOfferCreditEnforcement();
    $request = CustomerRequest::factory()->create();
    $offer = MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
    ]);
    app(MerchantOfferCreditService::class)->lockMerchant((int) $merchant->id);
    app(MerchantOfferCreditService::class)->consumeForOfferSubmit($merchant, $request, $offer, $admin);

    expect(PaymentTransaction::query()->count())->toBe(0)
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::PromotionalBonus)->value('payment_transaction_id'))->toBeNull()
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->value('payment_transaction_id'))->toBeNull()
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->value('amount'))->toBe(-1);
});

test('paid merchant credits without an owner payer are rejected', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::Cash->value,
            'paid_amount' => '5.000',
        ])
        ->assertSessionHasErrors('paid_amount');

    expect(PaymentTransaction::query()->count())->toBe(0)
        ->and(MerchantOfferCreditTransaction::query()->count())->toBe(0);
});
