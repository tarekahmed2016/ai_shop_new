<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\CompanyInfo;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->secondAdmin = User::factory()->create();
    $this->secondAdmin->assignRole('admin');
});

test('user create update and delete are logged over http', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Logged Staff',
            'email' => 'logged@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect();

    $user = User::where('email', 'logged@example.com')->first();

    expect(ActivityLog::where('event', Event::Created)->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [
            'name' => 'Updated Staff',
            'email' => $user->email,
            'phone' => '0123456789',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Updated)->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Deleted)->where('subject_id', $user->id)->exists())->toBeTrue()
        ->and(ActivityLog::where('event', Event::Deleted)->where('subject_id', $user->id)->first()->new_values)->toBeNull();
});

test('role create update delete and permission changes are logged over http', function () {
    $permission = Permission::firstOrCreate(['name' => 'settings.update', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->post(route('roles.store'), [
            'name' => 'editor',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect();

    $role = Role::where('name', 'editor')->first();
    $createLog = ActivityLog::where('event', Event::Created)->where('subject_id', $role->id)->first();

    expect($createLog)->not->toBeNull()
        ->and($createLog->metadata)->toHaveKey('permissions');

    $this->actingAs($this->admin)
        ->put(route('roles.update', $role), [
            'name' => 'publisher',
            'permissions' => [],
        ])
        ->assertRedirect();

    $updateLog = ActivityLog::where('event', Event::Updated)->where('subject_id', $role->id)->latest('id')->first();

    expect($updateLog)->not->toBeNull()
        ->and($updateLog->metadata['permissions']['old'])->toContain($permission->id)
        ->and($updateLog->metadata['permissions']['new'])->toBe([]);

    $this->actingAs($this->admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Deleted)->where('subject_id', $role->id)->exists())->toBeTrue();
});

test('company info create and update are logged over http', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'Acme Company',
            'name_en' => 'Acme Company',
            'phone' => '0123456789',
            'email' => 'hello@acme.test',
        ])
        ->assertRedirect();

    $companyInfo = CompanyInfo::first();

    expect(ActivityLog::where('event', Event::Created)->where('subject_id', $companyInfo->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'Updated Acme AR',
            'name_en' => 'Updated Acme',
            'phone' => '0123456789',
            'email' => 'hello@acme.test',
        ])
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Updated)->where('subject_id', $companyInfo->id)->exists())->toBeTrue();
});

test('service create update and delete are logged over http', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة مسجلة',
            'name_en' => 'Logged Service',
            'description_ar' => 'وصف',
            'description_en' => 'Professional consulting support.',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertRedirect();

    $service = Service::where('name_en', 'Logged Service')->first();

    expect(ActivityLog::where('event', Event::Created)->where('subject_id', $service->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('services.update', $service), [
            'name_ar' => $service->name_ar,
            'name_en' => 'Updated Service',
            'description_ar' => $service->description_ar,
            'description_en' => 'Updated',
            'ordering' => 0,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Updated)->where('subject_id', $service->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('services.destroy', $service))
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Deleted)->where('subject_id', $service->id)->exists())->toBeTrue();
});
