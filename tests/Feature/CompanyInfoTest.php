<?php

use App\Models\CompanyInfo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('company info get does not create a database row', function () {
    expect(CompanyInfo::count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('company-info.index'))
        ->assertOk();

    expect(CompanyInfo::count())->toBe(0);
});

test('guest cannot open company info', function () {
    $this->get(route('company-info.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open company info', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('company-info.index'))
        ->assertRedirect(route('login'));
});

test('admin can view company info with empty defaults', function () {
    $this->actingAs($this->admin)
        ->get(route('company-info.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CompanyInfo/CompanyInfoPage', false)
            ->where('companyInfo.name_ar', '')
            ->where('companyInfo.name_en', '')
            ->where('companyInfo.email', ''));
});

test('admin can create company info on first update with bilingual names', function () {
    expect(CompanyInfo::count())->toBe(0);

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة أكme',
            'name_en' => 'Acme Company',
            'phone' => '0123456789',
            'email' => 'hello@acme.test',
        ])
        ->assertRedirect();

    expect(CompanyInfo::count())->toBe(1)
        ->and(CompanyInfo::first()->name_ar)->toBe('شركة أكme')
        ->and(CompanyInfo::first()->name_en)->toBe('Acme Company');
});

test('admin can update both arabic and english company names', function () {
    CompanyInfo::create([
        'name_ar' => 'اسم قديم',
        'name_en' => 'Old Name',
        'phone' => '0123456789',
        'email' => 'hello@acme.test',
    ]);

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'اسم محدث',
            'name_en' => 'Updated Name',
            'phone' => '0123456789',
            'email' => 'hello@acme.test',
        ])
        ->assertRedirect();

    expect(CompanyInfo::first()->name_ar)->toBe('اسم محدث')
        ->and(CompanyInfo::first()->name_en)->toBe('Updated Name');
});

test('company info rejects invalid email', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'phone' => '0123456789',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');
});

test('company info accepts jpg logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'phone' => '0123456789',
            'email' => 'hello@acme.test',
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ])
        ->assertRedirect();

    expect(CompanyInfo::first()->attachment)->not->toBeNull();
});

test('company info accepts png logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertRedirect();
});

test('company info accepts webp logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'logo' => UploadedFile::fake()->create('logo.webp', 100, 'image/webp'),
        ])
        ->assertRedirect();
});

test('company info rejects svg logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'logo' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo');
});

test('company info rejects non image logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('logo');
});

test('company info rejects oversized logo', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), [
            'name_ar' => 'شركة',
            'name_en' => 'Company',
            'logo' => UploadedFile::fake()->image('logo.jpg')->size(5000),
        ])
        ->assertSessionHasErrors('logo');
});
