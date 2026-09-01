<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerOfferContactReveal;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOfferContactReveal>
 */
class CustomerOfferContactRevealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_request_id' => CustomerRequest::factory(),
            'merchant_offer_id' => MerchantOffer::factory(),
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'revealed_at' => now(),
        ];
    }
}
