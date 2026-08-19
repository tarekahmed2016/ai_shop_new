<?php

namespace Database\Factories;

use App\Enums\CertificateAwardType;
use App\Models\CertificateAward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateAward>
 */
class CertificateAwardFactory extends Factory
{
    protected $model = CertificateAward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CertificateAwardType::Certificate,
            'title_ar' => fake()->sentence(3),
            'title_en' => fake()->sentence(3),
            'issuer_ar' => fake()->optional()->company(),
            'issuer_en' => fake()->optional()->company(),
            'description_ar' => fake()->optional()->paragraph(),
            'description_en' => fake()->optional()->paragraph(),
            'issued_date' => fake()->optional()->date(),
            'external_url' => fake()->optional()->url(),
            'ordering' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function certificate(): static
    {
        return $this->state(fn () => ['type' => CertificateAwardType::Certificate]);
    }

    public function award(): static
    {
        return $this->state(fn () => ['type' => CertificateAwardType::Award]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
