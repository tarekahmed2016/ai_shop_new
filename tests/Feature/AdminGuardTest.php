<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

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
