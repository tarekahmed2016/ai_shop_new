<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('guest cannot open users index', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open users index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertRedirect(route('login'));
});

test('admin can view users index', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/UsersPage', false)
            ->has('users.data')
            ->has('statuses')
            ->has('roles'));
});

test('admin can create a user', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Jane Staff',
            'email' => 'jane@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('staff'))->toBeTrue();
});

test('admin can update a user', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->assignRole('staff');

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [
            'name' => 'New Name',
            'email' => $user->email,
            'phone' => '0987654321',
            'status' => 2,
            'role' => 'staff',
        ])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('New Name');
});

test('admin can delete a user', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect();

    expect(User::find($user->id))->toBeNull();
});
