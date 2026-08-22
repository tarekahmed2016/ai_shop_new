<?php

use App\Enums\MerchantMemberships\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Additive catalog + default permission rows for merchant offers.
 * Does not drop or rewrite existing customized permission sets.
 */
return new class extends Migration
{
    /**
     * Previous role defaults before offer permissions existed.
     *
     * @var array<string, list<string>>
     */
    private array $previousDefaults = [
        'owner' => [],
        'manager' => [
            'requests.view',
            'requests.view_details',
            'requests.dismiss',
            'activities.view',
            'activities.manage',
            'team.view',
            'team.add_staff',
            'team.edit_staff',
            'team.remove_staff',
            'merchant_profile.view',
        ],
        'staff' => [
            'requests.view',
            'requests.view_details',
            'activities.view',
            'team.view',
            'merchant_profile.view',
        ],
    ];

    /**
     * New default keys to grant when the membership still has the full previous default set.
     *
     * @var array<string, list<string>>
     */
    private array $newDefaultKeys = [
        'manager' => [
            'offers.view',
            'offers.create',
            'offers.update',
            'offers.withdraw',
        ],
        'staff' => [
            'offers.view',
        ],
    ];

    public function up(): void
    {
        $now = now();

        $catalog = [
            ['key' => 'offers.view', 'name_ar' => 'عرض العروض', 'name_en' => 'View offers', 'group_key' => 'offers'],
            ['key' => 'offers.create', 'name_ar' => 'إنشاء عرض', 'name_en' => 'Create offers', 'group_key' => 'offers'],
            ['key' => 'offers.update', 'name_ar' => 'تعديل عرض', 'name_en' => 'Update offers', 'group_key' => 'offers'],
            ['key' => 'offers.withdraw', 'name_ar' => 'سحب عرض', 'name_en' => 'Withdraw offers', 'group_key' => 'offers'],
        ];

        foreach ($catalog as $row) {
            DB::table('merchant_permissions')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'],
                    'group_key' => $row['group_key'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $permissionIdsByKey = DB::table('merchant_permissions')->pluck('id', 'key');
        $allKeys = $permissionIdsByKey->keys()->all();

        DB::table('merchant_user')->orderBy('id')->chunkById(100, function ($memberships) use ($permissionIdsByKey, $allKeys, $now): void {
            foreach ($memberships as $membership) {
                $existingKeys = DB::table('merchant_user_permissions')
                    ->join('merchant_permissions', 'merchant_permissions.id', '=', 'merchant_user_permissions.merchant_permission_id')
                    ->where('merchant_user_permissions.merchant_user_id', $membership->id)
                    ->pluck('merchant_permissions.key')
                    ->all();

                $keysToAdd = $this->keysToAddForMembership((string) $membership->role, $existingKeys, $allKeys);

                foreach ($keysToAdd as $key) {
                    $permissionId = $permissionIdsByKey[$key] ?? null;
                    if ($permissionId === null) {
                        continue;
                    }

                    $exists = DB::table('merchant_user_permissions')
                        ->where('merchant_user_id', $membership->id)
                        ->where('merchant_permission_id', $permissionId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('merchant_user_permissions')->insert([
                        'merchant_user_id' => $membership->id,
                        'merchant_permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    /**
     * @param  list<string>  $existingKeys
     * @param  list<string>  $allKeys
     * @return list<string>
     */
    private function keysToAddForMembership(string $role, array $existingKeys, array $allKeys): array
    {
        if ($role === Role::Owner->value) {
            return $allKeys;
        }

        if ($existingKeys === []) {
            return [];
        }

        $previous = match ($role) {
            Role::Manager->value => $this->previousDefaults['manager'],
            Role::Staff->value => $this->previousDefaults['staff'],
            default => [],
        };

        if ($previous === [] || array_diff($previous, $existingKeys) !== []) {
            return [];
        }

        return match ($role) {
            Role::Manager->value => $this->newDefaultKeys['manager'],
            Role::Staff->value => $this->newDefaultKeys['staff'],
            default => [],
        };
    }

    public function down(): void
    {
        $keys = ['offers.view', 'offers.create', 'offers.update', 'offers.withdraw'];
        $ids = DB::table('merchant_permissions')->whereIn('key', $keys)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('merchant_user_permissions')->whereIn('merchant_permission_id', $ids)->delete();
            DB::table('merchant_permissions')->whereIn('id', $ids)->delete();
        }
    }
};
