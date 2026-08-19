<?php

namespace Database\Factories;

use App\Enums\ClientPartnerType;
use App\Models\ClientPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPartner>
 */
class ClientPartnerFactory extends Factory
{
    protected $model = ClientPartner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ClientPartnerType::Client,
            'name_ar' => fake()->company(),
            'name_en' => fake()->company(),
            'website' => fake()->optional()->url(),
            'ordering' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function client(): static
    {
        return $this->state(fn () => ['type' => ClientPartnerType::Client]);
    }

    public function partner(): static
    {
        return $this->state(fn () => ['type' => ClientPartnerType::Partner]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
