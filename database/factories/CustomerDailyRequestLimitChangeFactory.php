<?php

namespace Database\Factories;

use App\Enums\CustomerDailyRequestLimitChanges\ChangeType;
use App\Models\Customer;
use App\Models\CustomerDailyRequestLimitChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDailyRequestLimitChange>
 */
class CustomerDailyRequestLimitChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'old_override' => null,
            'new_override' => 10,
            'effective_global_limit' => 5,
            'old_effective_limit' => 5,
            'new_effective_limit' => 10,
            'change_type' => ChangeType::SetOverride,
            'notes' => null,
            'changed_by_user_id' => null,
        ];
    }
}
