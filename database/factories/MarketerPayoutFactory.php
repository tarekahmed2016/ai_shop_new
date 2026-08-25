<?php

namespace Database\Factories;

use App\Enums\Payments\Method;
use App\Models\Marketer;
use App\Models\MarketerPayout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketerPayout>
 */
class MarketerPayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'marketer_id' => Marketer::factory(),
            'amount' => '4.000',
            'payment_method' => Method::BankTransfer,
            'reference' => null,
            'notes' => null,
            'paid_at' => now(),
            'created_by_user_id' => null,
        ];
    }
}
