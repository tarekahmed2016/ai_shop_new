<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantRequestMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantRequestMatch>
 */
class MerchantRequestMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'customer_request_id' => CustomerRequest::factory(),
            'matched_category_id' => Category::factory(),
            'matched_at' => now(),
        ];
    }
}
