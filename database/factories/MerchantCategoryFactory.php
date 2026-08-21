<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantCategory>
 */
class MerchantCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'category_id' => Category::factory(),
        ];
    }
}
