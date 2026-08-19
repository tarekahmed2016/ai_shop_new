<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->permission = Permission::firstOrCreate([
        'name' => 'settings.update',
        'guard_name' => 'web',
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('guest cannot open roles index', function () {
    $this->get(route('roles.index'))
        ->assertRedirect(route('login'));
});

test('admin can view roles index', function () {
    $this->actingAs($this->admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/RolesPage', false)
            ->has('roles.data')
            ->has('permissions'));
});

test('admin can create a role', function () {
    $this->actingAs($this->admin)
        ->post(route('roles.store'), [
            'name' => 'editor',
            'permissions' => [$this->permission->id],
        ])
        ->assertRedirect();

    $role = Role::where('name', 'editor')->first();

    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('settings.update'))->toBeTrue();
});

test('admin can update a role', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->put(route('roles.update', $role), [
            'name' => 'publisher',
            'permissions' => [$this->permission->id],
        ])
        ->assertRedirect();

    expect($role->fresh()->name)->toBe('publisher');
});

test('admin can delete a role', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect();

    expect(Role::find($role->id))->toBeNull();
});
