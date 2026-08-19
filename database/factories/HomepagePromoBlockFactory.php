<?php

namespace Database\Factories;

use App\Enums\HomepagePromoLayout;
use App\Enums\HomepagePromoType;
use App\Models\HomepagePromoBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepagePromoBlock>
 */
class HomepagePromoBlockFactory extends Factory
{
    protected $model = HomepagePromoBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => HomepagePromoType::FeatureBand,
            'title_ar' => fake()->sentence(3),
            'title_en' => fake()->sentence(3),
            'description_ar' => fake()->optional()->paragraph(),
            'description_en' => fake()->optional()->paragraph(),
            'cta_text_ar' => fake()->optional()->words(2, true),
            'cta_text_en' => fake()->optional()->words(2, true),
            'cta_url' => fake()->optional()->url(),
            'layout_variant' => HomepagePromoLayout::ContentLeft,
            'ordering' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function featureBand(): static
    {
        return $this->state(fn () => ['type' => HomepagePromoType::FeatureBand]);
    }

    public function promoStrip(): static
    {
        return $this->state(fn () => ['type' => HomepagePromoType::PromoStrip]);
    }

    public function businessCta(): static
    {
        return $this->state(fn () => ['type' => HomepagePromoType::BusinessCta]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
