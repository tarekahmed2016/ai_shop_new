<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use App\Models\PlatformSettingChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSettingChange>
 */
class PlatformSettingChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT,
            'old_value' => '5',
            'new_value' => '10',
            'notes' => null,
            'changed_by_user_id' => null,
        ];
    }
}
