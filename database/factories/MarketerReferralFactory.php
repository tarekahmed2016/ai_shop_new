<?php

namespace Database\Factories;

use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketerReferral>
 */
class MarketerReferralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $marketer = Marketer::factory();

        return [
            'marketer_id' => $marketer,
            'referred_user_id' => User::factory(),
            'referral_code' => strtoupper(fake()->unique()->regexify('[A-Z0-9]{8}')),
            'registered_at' => now(),
        ];
    }
}
