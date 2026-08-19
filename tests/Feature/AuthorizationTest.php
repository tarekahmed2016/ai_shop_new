<?php

use App\Models\CompanyInfo;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

test('non admin cannot access users index', function () {
    $this->actingAs($this->user)
        ->get(route('users.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot store users', function () {
    $this->actingAs($this->user)
        ->post(route('users.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect(route('login'));
});

test('non admin cannot update users', function () {
    $target = User::factory()->create();
    $target->assignRole('staff');

    $this->actingAs($this->user)
        ->put(route('users.update', $target), [
            'name' => 'Changed',
            'email' => $target->email,
            'phone' => '0123456789',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect(route('login'));
});

test('non admin cannot delete users', function () {
    $target = User::factory()->create();
    $target->assignRole('staff');

    $this->actingAs($this->user)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('login'));
});

test('non admin cannot access roles index', function () {
    $this->actingAs($this->user)
        ->get(route('roles.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot store roles', function () {
    $this->actingAs($this->user)
        ->post(route('roles.store'), ['name' => 'blocked-role'])
        ->assertRedirect(route('login'));
});

test('non admin cannot update roles', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($this->user)
        ->put(route('roles.update', $role), ['name' => 'blocked-editor'])
        ->assertRedirect(route('login'));
});

test('non admin cannot delete roles', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($this->user)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('login'));
});

test('guest cannot access company info', function () {
    $this->get(route('company-info.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot access company info', function () {
    $this->actingAs($this->user)
        ->get(route('company-info.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot update company info', function () {
    $this->actingAs($this->user)
        ->put(route('company-info.update'), [
            'name_ar' => 'Blocked Company',
            'name_en' => 'Blocked Company',
            'phone' => '0123456789',
            'email' => 'blocked@example.com',
        ])
        ->assertRedirect(route('login'));

    expect(CompanyInfo::count())->toBe(0);
});

test('admin can access and update company info', function () {
    $this->actingAs($this->admin)
        ->get(route('company-info.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'Allowed Company',
            'name_en' => 'Allowed Company',
            'phone' => '0123456789',
            'email' => 'allowed@example.com',
        ])
        ->assertRedirect();

    expect(CompanyInfo::first()->name_en)->toBe('Allowed Company');
});

test('non admin cannot access services index', function () {
    $this->actingAs($this->user)
        ->get(route('services.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot store services', function () {
    $this->actingAs($this->user)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة محظورة',
            'name_en' => 'Blocked Service',
            'description_ar' => 'محظور',
            'description_en' => 'Blocked',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertRedirect(route('login'));
});

test('non admin cannot update services', function () {
    $service = Service::factory()->create();

    $this->actingAs($this->user)
        ->put(route('services.update', $service), [
            'name_ar' => 'خدمة',
            'name_en' => 'Blocked Update',
            'description_ar' => 'محظور',
            'description_en' => 'Blocked',
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertRedirect(route('login'));
});

test('non admin cannot delete services', function () {
    $service = Service::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('services.destroy', $service))
        ->assertRedirect(route('login'));
});

test('non admin cannot use services next ordering endpoint', function () {
    $this->actingAs($this->user)
        ->get(route('services.next-ordering'))
        ->assertRedirect(route('login'));
});
