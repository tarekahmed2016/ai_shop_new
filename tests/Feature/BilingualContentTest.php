<?php

use App\Models\CompanyInfo;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('services table uses bilingual content columns', function () {
    expect(Schema::hasColumns('services', ['name_ar', 'name_en', 'description_ar', 'description_en']))->toBeTrue()
        ->and(Schema::hasColumn('services', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('services', 'description'))->toBeFalse();
});

test('company info table uses bilingual name columns', function () {
    expect(Schema::hasColumns('company_info', ['name_ar', 'name_en']))->toBeTrue()
        ->and(Schema::hasColumn('company_info', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('company_info', 'hero_title_ar'))->toBeTrue()
        ->and(Schema::hasColumn('company_info', 'website'))->toBeTrue();
});

test('migrated legacy service content is preserved in arabic fields', function () {
    $service = Service::factory()->create([
        'name_ar' => 'محتوى قديم',
        'name_en' => '',
        'description_ar' => 'وصف قديم',
        'description_en' => null,
    ]);

    expect($service->fresh()->name_ar)->toBe('محتوى قديم')
        ->and($service->fresh()->description_ar)->toBe('وصف قديم');
});

test('public homepage receives bilingual service data', function () {
    Service::factory()->create([
        'name_ar' => 'خدمة عربية',
        'name_en' => 'English Service',
        'description_ar' => 'وصف عربي',
        'description_en' => 'English description',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('services.0.name_ar', 'خدمة عربية')
            ->where('services.0.name_en', 'English Service')
            ->where('services.0.description_ar', 'وصف عربي')
            ->where('services.0.description_en', 'English description')
            ->missing('services.0.name')
            ->missing('services.0.description')
            ->missing('services.0.is_active')
            ->missing('services.0.ordering')
            ->missing('services.0.id'));
});

test('public homepage receives bilingual company info', function () {
    CompanyInfo::create([
        'name_ar' => 'شركة أكme',
        'name_en' => 'Acme Corp',
        'phone' => '0123456789',
        'email' => 'hello@acme.test',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyInfo.name_ar', 'شركة أكme')
            ->where('companyInfo.name_en', 'Acme Corp')
            ->missing('companyInfo.name'));
});

test('public service payload supports fallback when one language is empty', function () {
    Service::factory()->create([
        'name_ar' => 'خدمة عربية فقط',
        'name_en' => '',
        'description_ar' => 'وصف عربي',
        'description_en' => null,
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('services.0.name_ar', 'خدمة عربية فقط')
            ->where('services.0.name_en', ''));
});

test('projects table uses bilingual content columns', function () {
    expect(Schema::hasColumns('projects', [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'client_name_ar',
        'client_name_en',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('projects', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('projects', 'description'))->toBeFalse()
        ->and(Schema::hasColumn('projects', 'client_name'))->toBeFalse();
});

test('migrated legacy project content is preserved in arabic fields', function () {
    $project = Project::factory()->create([
        'name_ar' => 'محتوى مشروع قديم',
        'name_en' => '',
        'description_ar' => 'وصف قديم',
        'description_en' => null,
        'client_name_ar' => 'عميل قديم',
        'client_name_en' => null,
    ]);

    expect($project->fresh()->name_ar)->toBe('محتوى مشروع قديم')
        ->and($project->fresh()->description_ar)->toBe('وصف قديم')
        ->and($project->fresh()->client_name_ar)->toBe('عميل قديم');
});

test('public homepage receives bilingual project data', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع عربي',
        'name_en' => 'English Project',
        'client_name_ar' => 'عميل عربي',
        'client_name_en' => 'English Client',
        'description_ar' => 'وصف عربي',
        'description_en' => 'English description',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.name_ar', 'مشروع عربي')
            ->where('projects.0.name_en', 'English Project')
            ->where('projects.0.client_name_ar', 'عميل عربي')
            ->where('projects.0.client_name_en', 'English Client')
            ->where('projects.0.description_ar', 'وصف عربي')
            ->where('projects.0.description_en', 'English description')
            ->missing('projects.0.name')
            ->missing('projects.0.client_name')
            ->missing('projects.0.description')
            ->missing('projects.0.is_active')
            ->missing('projects.0.ordering')
            ->missing('projects.0.id'));
});
