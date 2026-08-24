<?php

namespace Database\Factories;

use App\Enums\Marketers\Status;
use App\Models\Marketer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Marketer>
 */
class MarketerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'referral_code' => strtoupper(fake()->unique()->regexify('[A-Z0-9]{8}')),
            'status' => Status::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Inactive,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Pending,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Rejected,
        ]);
    }
}
