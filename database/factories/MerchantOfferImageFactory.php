<?php

namespace Database\Factories;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantOfferImage>
 */
class MerchantOfferImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_offer_id' => MerchantOffer::factory(),
            'path' => 'merchant-offers/'.fake()->uuid().'.jpg',
            'original_name' => 'offer.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'sort_order' => 0,
        ];
    }
}
