<?php

namespace Database\Factories;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Models\Merchant;
use App\Models\MerchantOfferCreditTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantOfferCreditTransaction>
 */
class MerchantOfferCreditTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'type' => TransactionType::ManualAdd,
            'source' => TransactionSource::ManualAdjustment,
            'amount' => 10,
            'paid_amount' => null,
            'reference' => null,
            'notes' => null,
            'created_by_user_id' => null,
            'customer_request_id' => null,
            'merchant_offer_id' => null,
        ];
    }
}
