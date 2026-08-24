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
            'daily_request_limit_override' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspension_types' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Inactive,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'contact_information_in_request',
            'suspension_types' => ['phone'],
        ]);
    }
}
