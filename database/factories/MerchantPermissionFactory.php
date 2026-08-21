<?php

namespace Database\Factories;

use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\MerchantPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantPermission>
 */
class MerchantPermissionFactory extends Factory
{
    protected $model = MerchantPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->randomElement(PermissionKey::cases());

        return [
            'key' => $key->value,
            'name_ar' => $key->nameAr(),
            'name_en' => $key->nameEn(),
            'group_key' => $key->groupKey(),
        ];
    }
}
