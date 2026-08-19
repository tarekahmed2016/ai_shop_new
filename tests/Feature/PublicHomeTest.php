<?php

use App\Models\CompanyInfo;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('homepage is accessible to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/HomePage', false)
            ->has('companyInfo')
            ->has('services')
            ->has('projects')
            ->has('teamMembers')
            ->has('clients')
            ->has('partners'));
});

test('homepage is accessible without authentication', function () {
    $this->assertGuest();

    $this->get('/')->assertOk();
});

test('homepage get performs no database writes', function () {
    expect(CompanyInfo::count())->toBe(0)
        ->and(Service::count())->toBe(0);

    $this->get(route('home'))->assertOk();

    expect(CompanyInfo::count())->toBe(0)
        ->and(Service::count())->toBe(0);
});

test('homepage works when company info does not exist', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyInfo.name_ar', '')
            ->where('companyInfo.name_en', '')
            ->where('companyInfo.phone', '')
            ->where('companyInfo.email', '')
            ->where('companyInfo.logo', null));
});

test('homepage works when no services exist', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('services', [])
            ->where('projects', [])
            ->where('teamMembers', [])
            ->where('clients', [])
            ->where('partners', []));
});

test('homepage passes company info correctly', function () {
    $companyInfo = CompanyInfo::create([
        'name_ar' => 'شركة أكme',
        'name_en' => 'Acme Corp',
        'phone' => '0123456789',
        'email' => 'hello@acme.test',
    ]);

    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.jpg')->store('company-info', 'public');
    $companyInfo->attachment()->create([
        'name' => 'logo.jpg',
        'path' => $path,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyInfo.name_ar', 'شركة أكme')
            ->where('companyInfo.name_en', 'Acme Corp')
            ->where('companyInfo.phone', '0123456789')
            ->where('companyInfo.email', 'hello@acme.test')
            ->where('companyInfo.logo', asset('storage/'.$path)));
});

test('homepage shows only active services in ordering', function () {
    Storage::fake('public');

    $first = Service::factory()->create([
        'name_ar' => 'الخدمة الأولى',
        'name_en' => 'First Service',
        'ordering' => 1,
        'is_active' => true,
    ]);
    $second = Service::factory()->create([
        'name_ar' => 'الخدمة الثانية',
        'name_en' => 'Second Service',
        'ordering' => 2,
        'is_active' => true,
    ]);
    Service::factory()->inactive()->create([
        'name_ar' => 'خدمة مخفية',
        'name_en' => 'Hidden Service',
        'ordering' => 0,
    ]);

    $firstPath = UploadedFile::fake()->image('first.jpg')->store('services', 'public');
    $first->attachment()->create(['name' => 'first.jpg', 'path' => $firstPath]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('services', 2)
            ->where('services.0.name_en', 'First Service')
            ->where('services.1.name_en', 'Second Service')
            ->where('services.0.image', asset('storage/'.$firstPath))
            ->missing('services.0.id')
            ->missing('services.0.ordering')
            ->missing('services.0.is_active'));
});

test('homepage does not expose admin-only data', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Service::factory()->create([
        'name_ar' => 'خدمة عامة',
        'name_en' => 'Public Service',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('users')
            ->missing('roles')
            ->missing('permissions')
            ->missing('activityLogs')
            ->where('services.0.name_en', 'Public Service')
            ->missing('services.0.is_active')
            ->missing('services.0.ordering')
            ->missing('services.0.id'));
});

test('homepage shows only active projects in ordering', function () {
    Storage::fake('public');

    $first = Project::factory()->create([
        'name_ar' => 'المشروع الأول',
        'name_en' => 'First Project',
        'ordering' => 1,
        'is_active' => true,
    ]);
    Project::factory()->create([
        'name_ar' => 'المشروع الثاني',
        'name_en' => 'Second Project',
        'ordering' => 2,
        'is_active' => true,
    ]);
    Project::factory()->inactive()->create([
        'name_ar' => 'مشروع مخفي',
        'name_en' => 'Hidden Project',
        'ordering' => 0,
    ]);

    $firstPath = UploadedFile::fake()->image('first.jpg')->store('projects', 'public');
    $first->attachment()->create(['name' => 'first.jpg', 'path' => $firstPath]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            ->where('projects.0.name_en', 'First Project')
            ->where('projects.1.name_en', 'Second Project')
            ->where('projects.0.name_ar', 'المشروع الأول')
            ->where('projects.0.image', asset('storage/'.$firstPath))
            ->missing('projects.0.id')
            ->missing('projects.0.ordering')
            ->missing('projects.0.is_active'));
});

test('homepage works with zero projects', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects', []));
});

test('homepage public project payload excludes admin-only fields', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع عام',
        'name_en' => 'Public Project',
        'client_name_ar' => 'عميل أ',
        'client_name_en' => 'Client A',
        'description_ar' => 'وصف عربي',
        'description_en' => 'English description',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.name_ar', 'مشروع عام')
            ->where('projects.0.name_en', 'Public Project')
            ->where('projects.0.client_name_ar', 'عميل أ')
            ->where('projects.0.client_name_en', 'Client A')
            ->where('projects.0.description_ar', 'وصف عربي')
            ->where('projects.0.description_en', 'English description')
            ->missing('projects.0.name')
            ->missing('projects.0.client_name')
            ->missing('projects.0.description')
            ->missing('projects.0.is_active')
            ->missing('projects.0.ordering')
            ->missing('projects.0.id')
            ->missing('projects.0.created_at')
            ->missing('projects.0.updated_at'));
});

test('authenticated users can still view the public homepage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/HomePage', false));
});
