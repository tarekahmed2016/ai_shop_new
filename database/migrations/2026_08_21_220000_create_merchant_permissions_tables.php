<?php

/**
 * Migration uses query builder only (no Eloquent) for backfill safety.
 */

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantPermissions\PermissionKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('group_key');
            $table->timestamps();

            $table->index('group_key');
        });

        Schema::create('merchant_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_user_id')->constrained('merchant_user')->cascadeOnDelete();
            $table->foreignId('merchant_permission_id')->constrained('merchant_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['merchant_user_id', 'merchant_permission_id'], 'merchant_user_permission_unique');
        });

        $now = now();

        foreach (PermissionKey::cases() as $permission) {
            DB::table('merchant_permissions')->updateOrInsert(
                ['key' => $permission->value],
                [
                    'name_ar' => $permission->nameAr(),
                    'name_en' => $permission->nameEn(),
                    'group_key' => $permission->groupKey(),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $permissionIdsByKey = DB::table('merchant_permissions')->pluck('id', 'key');

        DB::table('merchant_user')->orderBy('id')->chunkById(100, function ($memberships) use ($permissionIdsByKey, $now): void {
            foreach ($memberships as $membership) {
                $role = (string) $membership->role;

                $keys = match ($role) {
                    Role::Owner->value => PermissionKey::ownerDefaults(),
                    Role::Manager->value => PermissionKey::managerDefaults(),
                    Role::Staff->value => PermissionKey::staffDefaults(),
                    default => PermissionKey::staffDefaults(),
                };

                foreach ($keys as $key) {
                    $permissionId = $permissionIdsByKey[$key->value] ?? null;

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

    public function down(): void
    {
        Schema::dropIfExists('merchant_user_permissions');
        Schema::dropIfExists('merchant_permissions');
    }
};
