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

        $name = AdminPermissionCatalog::MANAGE_ADMIN_ROLE;

        Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        $admin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin !== null) {
            $admin->givePermissionTo($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Additive catalog row; keep assigned grants intact on rollback.
    }
};
