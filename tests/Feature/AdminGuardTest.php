<?php

use App\Models\User;
use App\Support\AdminPermissionCatalog;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function staffUserPayload(User $user, string $role, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => 1,
        'role' => $role,
    ], $overrides);
}

test('cannot delete the last administrator', function () {
    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $this->admin))
        ->assertSessionHasErrors('user');

    expect(User::find($this->admin->id))->not->toBeNull();
});

test('cannot demote the last administrator', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'phone' => $this->admin->phone,
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('role');

    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('cannot delete the administrator role', function () {
    $adminRole = Role::where('name', 'admin')->first();

    $this->actingAs($this->admin)
        ->delete(route('roles.destroy', $adminRole))
        ->assertSessionHasErrors('role');

    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});

test('can delete a non admin user when another admin exists', function () {
    $secondAdmin = User::factory()->create();
    $secondAdmin->assignRole('admin');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $staff))
        ->assertRedirect();

    expect(User::find($staff->id))->toBeNull();
});

test('users.update alone can edit a normal user but cannot assign the admin role', function () {
    $editor = adminWithPermissions(['users.update']);
    $staff = User::factory()->create(['name' => 'Before']);
    $staff->assignRole('staff');

    $this->actingAs($editor)
        ->put(route('users.update', $staff), staffUserPayload($staff, 'staff', [
            'name' => 'After',
        ]))
        ->assertRedirect();

    expect($staff->fresh()->name)->toBe('After')
        ->and($staff->fresh()->hasRole('staff'))->toBeTrue();

    $this->actingAs($editor)
        ->put(route('users.update', $staff), staffUserPayload($staff->fresh(), 'admin'))
        ->assertForbidden();

    expect($staff->fresh()->hasRole('admin'))->toBeFalse()
        ->and($staff->fresh()->hasRole('staff'))->toBeTrue();
});

test('users.update can edit an admin user when the admin role is unchanged', function () {
    $editor = adminWithPermissions(['users.update']);
    $otherAdmin = User::factory()->create(['name' => 'Admin Before']);
    $otherAdmin->assignRole('admin');

    $this->actingAs($editor)
        ->put(route('users.update', $otherAdmin), staffUserPayload($otherAdmin, 'admin', [
            'name' => 'Admin After',
        ]))
        ->assertRedirect();

    expect($otherAdmin->fresh()->name)->toBe('Admin After')
        ->and($otherAdmin->fresh()->hasRole('admin'))->toBeTrue();
});

test('users.update alone cannot remove the admin role', function () {
    $editor = adminWithPermissions(['users.update']);
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    $this->actingAs($editor)
        ->put(route('users.update', $otherAdmin), staffUserPayload($otherAdmin, 'staff'))
        ->assertForbidden();

    expect($otherAdmin->fresh()->hasRole('admin'))->toBeTrue();
});

test('users.create alone cannot create a user with the admin role', function () {
    $editor = adminWithPermissions(['users.create']);

    $this->actingAs($editor)
        ->post(route('users.store'), [
            'name' => 'Escalated',
            'email' => 'escalated@example.test',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'admin',
        ])
        ->assertForbidden();

    expect(User::where('email', 'escalated@example.test')->exists())->toBeFalse();
});

test('manage-admin-role can assign and remove the admin role except for the last admin', function () {
    $manager = adminWithPermissions([
        'users.update',
        'users.create',
        AdminPermissionCatalog::MANAGE_ADMIN_ROLE,
    ]);
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($manager)
        ->put(route('users.update', $staff), staffUserPayload($staff, 'admin'))
        ->assertRedirect();

    expect($staff->fresh()->hasRole('admin'))->toBeTrue();

    $this->actingAs($manager)
        ->put(route('users.update', $staff->fresh()), staffUserPayload($staff->fresh(), 'staff'))
        ->assertRedirect();

    expect($staff->fresh()->hasRole('admin'))->toBeFalse()
        ->and($staff->fresh()->hasRole('staff'))->toBeTrue();

    $this->actingAs($manager)
        ->post(route('users.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@example.test',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'admin',
        ])
        ->assertRedirect();

    $createdAdmin = User::where('email', 'new-admin@example.test')->first();
    expect($createdAdmin?->hasRole('admin'))->toBeTrue();

    $this->actingAs($manager)
        ->put(route('users.update', $createdAdmin), staffUserPayload($createdAdmin, 'staff'))
        ->assertRedirect();

    $this->actingAs($manager)
        ->put(route('users.update', $this->admin), staffUserPayload($this->admin, 'staff'))
        ->assertRedirect();

    $this->actingAs($manager)
        ->put(route('users.update', $manager), staffUserPayload($manager, 'staff'))
        ->assertSessionHasErrors('role');

    expect($manager->fresh()->hasRole('admin'))->toBeTrue();
});

test('seeded admin still has manage-admin-role and can promote a staff user', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    expect($this->admin->can(AdminPermissionCatalog::MANAGE_ADMIN_ROLE))->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('users.update', $staff), staffUserPayload($staff, 'admin'))
        ->assertRedirect();

    expect($staff->fresh()->hasRole('admin'))->toBeTrue();
});
