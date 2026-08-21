<?php

namespace Database\Factories;

use App\Enums\Customers\Status;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'whatsapp_id' => null,
            'email' => fake()->optional()->safeEmail(),
            'status' => Status::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Inactive,
        ]);
    }
}
