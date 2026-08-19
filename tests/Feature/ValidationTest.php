<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('user creation requires name', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => '',
            'email' => 'jane@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('name');
});

test('user creation requires valid email', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Jane Staff',
            'email' => 'invalid-email',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('email');
});

test('user creation rejects duplicate email', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Jane Staff',
            'email' => 'duplicate@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('email');
});

test('user creation rejects invalid role', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Jane Staff',
            'email' => 'jane@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'missing-role',
        ])
        ->assertSessionHasErrors('role');
});

test('user creation rejects invalid status', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Jane Staff',
            'email' => 'jane@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 99,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('status');
});

test('user creation requires password with minimum length', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Jane Staff',
            'email' => 'jane@example.com',
            'phone' => '0123456789',
            'password' => 'short',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('password');
});

test('role creation requires name', function () {
    $this->actingAs($this->admin)
        ->post(route('roles.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('role creation rejects duplicate name', function () {
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->post(route('roles.store'), ['name' => 'editor'])
        ->assertSessionHasErrors('name');
});

test('role creation rejects invalid permission ids', function () {
    $this->actingAs($this->admin)
        ->post(route('roles.store'), [
            'name' => 'editor',
            'permissions' => [999999],
        ])
        ->assertSessionHasErrors('permissions.0');
});

test('role update rejects invalid permission ids', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->put(route('roles.update', $role), [
            'name' => 'editor',
            'permissions' => [999999],
        ])
        ->assertSessionHasErrors('permissions.0');
});

test('service creation requires name_ar', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => '',
            'name_en' => 'Valid Service',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertSessionHasErrors('name_ar');
});

test('service creation requires name_en', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => '',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertSessionHasErrors('name_en');
});

test('service creation rejects name_ar over max length', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => str_repeat('a', 256),
            'name_en' => 'Valid Service',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertSessionHasErrors('name_ar');
});

test('service creation rejects description_en over max length', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => 'Valid Service',
            'description_en' => str_repeat('a', 15001),
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertSessionHasErrors('description_en');
});

test('service creation rejects invalid ordering', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => 'Valid Service',
            'ordering' => -1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertSessionHasErrors('ordering');
});

test('service creation rejects invalid image type', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => 'Valid Service',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->create('service.svg', 100, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('image');
});

test('service creation rejects oversized image', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => 'Valid Service',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg')->size(5000),
        ])
        ->assertSessionHasErrors('image');
});

test('service creation accepts png image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => 'PNG Service',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.png'),
        ])
        ->assertRedirect();
});
