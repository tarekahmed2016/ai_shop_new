<?php

use App\Support\AdminPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = AdminPermissionCatalog::names();

        foreach ($names as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin !== null) {
            $admin->givePermissionTo($names);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions are additive catalog rows; do not delete names that
        // predated this migration and may still be assigned in production.
    }
};
