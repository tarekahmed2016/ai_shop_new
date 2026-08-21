<?php

namespace Database\Factories;

use App\Enums\RequestMatches\Status;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\RequestMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestMatch>
 */
class RequestMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_request_id' => CustomerRequest::factory(),
            'merchant_id' => Merchant::factory(),
            'status' => Status::Pending,
            'matched_at' => now(),
        ];
    }

    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Viewed,
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Dismissed,
        ]);
    }
}
