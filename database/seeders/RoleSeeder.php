<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'services.view',
            'services.create',
            'services.update',
            'services.delete',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'team-members.view',
            'team-members.create',
            'team-members.update',
            'team-members.delete',
            'clients-partners.view',
            'clients-partners.create',
            'clients-partners.update',
            'clients-partners.delete',
            'certificates-awards.view',
            'certificates-awards.create',
            'certificates-awards.update',
            'certificates-awards.delete',
            'contact-messages.view',
            'contact-messages.update',
            'contact-messages.delete',
            'settings.update',
            'pages.view',
            'pages.create',
            'pages.update',
            'pages.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $admin->syncPermissions($permissions);
    }
}
