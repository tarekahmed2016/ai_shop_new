<?php

namespace Database\Factories;

use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantOffer>
 */
class MerchantOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'customer_request_id' => CustomerRequest::factory(),
            'merchant_id' => Merchant::factory(),
            'price' => '12.500',
            'currency' => MerchantOffer::CURRENCY,
            'availability_status' => AvailabilityStatus::Available,
            'notes' => fake()->optional()->sentence(),
            'valid_until' => null,
            'status' => Status::Submitted,
            'submitted_at' => now(),
            'withdrawn_at' => null,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => [
            'status' => Status::Withdrawn,
            'withdrawn_at' => now(),
        ]);
    }

    public function invalidated(): static
    {
        return $this->state(fn () => [
            'status' => Status::Invalidated,
        ]);
    }
}
