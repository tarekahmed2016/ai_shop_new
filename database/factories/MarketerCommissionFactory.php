<?php

namespace Database\Factories;

use App\Enums\MarketerCommissions\CommissionType;
use App\Enums\MarketerCommissions\Status;
use App\Enums\Payments\Type as PaymentType;
use App\Models\Marketer;
use App\Models\MarketerCommission;
use App\Models\MarketerReferral;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketerCommission>
 */
class MarketerCommissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'marketer_id' => Marketer::factory(),
            'marketer_referral_id' => MarketerReferral::factory(),
            'payment_transaction_id' => PaymentTransaction::factory(),
            'referred_user_id' => User::factory(),
            'payment_type' => PaymentType::CustomerExtraRequests,
            'payment_amount' => '10.000',
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => '10.000',
            'commission_amount' => '1.000',
            'status' => Status::Approved,
            'earned_at' => now(),
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Pending,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Cancelled,
        ]);
    }
}
