<?php

use App\Models\CompanyInfo;
use App\Models\HeroSlide;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin can save rich text html in service description fields', function () {
    $html = '<p><strong>Bold</strong> service description</p><ul><li>Item one</li></ul>';

    $this->actingAs($this->admin)
        ->post(route('services.store'), [
            'name_ar' => 'خدمة',
            'name_en' => 'Service',
            'description_ar' => $html,
            'description_en' => $html,
            'ordering' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ])
        ->assertRedirect();

    $service = Service::first();

    expect($service->description_en)->toBe($html);
});

test('public homepage exposes configured social links from company info', function () {
    CompanyInfo::query()->delete();

    CompanyInfo::create([
        'name_ar' => 'شركة',
        'name_en' => 'Company',
        'facebook' => 'https://facebook.com/acme',
        'instagram' => 'https://instagram.com/acme',
        'linkedin' => 'https://linkedin.com/company/acme',
        'x_twitter' => 'https://x.com/acme',
        'website' => 'https://acme.test',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/HomePage', false)
            ->where('companyInfo.facebook', 'https://facebook.com/acme')
            ->where('companyInfo.instagram', 'https://instagram.com/acme')
            ->where('companyInfo.linkedin', 'https://linkedin.com/company/acme')
            ->where('companyInfo.x_twitter', 'https://x.com/acme')
            ->where('companyInfo.website', 'https://acme.test'));
});

test('hero slide update route pattern remains record specific post with method spoofing', function () {
    $slide = HeroSlide::factory()->create(['title_en' => 'Route Guard']);
    $slide->attachment()->create(['name' => 'hero.jpg', 'path' => 'hero-slides/test.jpg']);
    Storage::disk('public')->put('hero-slides/test.jpg', 'test');

    $this->actingAs($this->admin)
        ->post(route('hero-slides.update', $slide), [
            '_method' => 'put',
            'title_ar' => 'x',
            'title_en' => 'Updated Route Guard',
            'description_ar' => '<p>Rich text</p>',
            'description_en' => '<p>Rich text</p>',
            'ordering' => 1,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($slide->fresh()->title_en)->toBe('Updated Route Guard')
        ->and($slide->fresh()->description_en)->toBe('<p>Rich text</p>');
});
