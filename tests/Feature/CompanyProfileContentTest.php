<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\CompanyInfo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function validCompanyProfilePayload(array $overrides = []): array
{
    return array_merge([
        'name_ar' => 'شركة أكme',
        'name_en' => 'Acme Company',
        'phone' => '0123456789',
        'email' => 'hello@acme.test',
        'hero_title_ar' => 'مرحباً بكم في أكme',
        'hero_title_en' => 'Welcome to Acme',
        'hero_description_ar' => 'وصف عربي للقسم الرئيسي.',
        'hero_description_en' => 'English hero description.',
        'about_ar' => 'نبذة عربية عن الشركة.',
        'about_en' => 'English about content.',
        'vision_ar' => 'رؤيتنا العربية.',
        'vision_en' => 'Our English vision.',
        'mission_ar' => 'رسالتنا العربية.',
        'mission_en' => 'Our English mission.',
        'address_ar' => 'الرياض، المملكة العربية السعودية',
        'address_en' => 'Riyadh, Saudi Arabia',
        'website' => 'https://acme.test',
        'facebook' => 'https://facebook.com/acme',
        'instagram' => 'https://instagram.com/acme',
        'linkedin' => 'https://linkedin.com/company/acme',
        'x_twitter' => 'https://x.com/acme',
    ], $overrides);
}

test('company info table contains profile content columns', function () {
    expect(Schema::hasColumns('company_info', [
        'hero_title_ar',
        'hero_title_en',
        'hero_description_ar',
        'hero_description_en',
        'about_ar',
        'about_en',
        'vision_ar',
        'vision_en',
        'mission_ar',
        'mission_en',
        'address_ar',
        'address_en',
        'website',
        'facebook',
        'instagram',
        'linkedin',
        'x_twitter',
    ]))->toBeTrue();
});

test('admin can save bilingual hero fields', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload())
        ->assertRedirect();

    $companyInfo = CompanyInfo::first();

    expect($companyInfo->hero_title_ar)->toBe('مرحباً بكم في أكme')
        ->and($companyInfo->hero_title_en)->toBe('Welcome to Acme')
        ->and($companyInfo->hero_description_ar)->toBe('وصف عربي للقسم الرئيسي.')
        ->and($companyInfo->hero_description_en)->toBe('English hero description.');
});

test('admin can save bilingual about content', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload())
        ->assertRedirect();

    expect(CompanyInfo::first()->about_ar)->toBe('نبذة عربية عن الشركة.')
        ->and(CompanyInfo::first()->about_en)->toBe('English about content.');
});

test('admin can save vision and mission content', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload())
        ->assertRedirect();

    $companyInfo = CompanyInfo::first();

    expect($companyInfo->vision_ar)->toBe('رؤيتنا العربية.')
        ->and($companyInfo->vision_en)->toBe('Our English vision.')
        ->and($companyInfo->mission_ar)->toBe('رسالتنا العربية.')
        ->and($companyInfo->mission_en)->toBe('Our English mission.');
});

test('admin can save bilingual address', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload())
        ->assertRedirect();

    expect(CompanyInfo::first()->address_ar)->toBe('الرياض، المملكة العربية السعودية')
        ->and(CompanyInfo::first()->address_en)->toBe('Riyadh, Saudi Arabia');
});

test('company info rejects invalid website url', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload([
            'website' => 'not-a-url',
        ]))
        ->assertSessionHasErrors('website');
});

test('company info rejects invalid social url', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload([
            'facebook' => 'invalid-url',
        ]))
        ->assertSessionHasErrors('facebook');
});

test('company info profile update records activity for content fields', function () {
    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload([
            'hero_title_en' => 'Initial Hero',
        ]))
        ->assertRedirect();

    $companyInfo = CompanyInfo::first();

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), validCompanyProfilePayload([
            'hero_title_en' => 'Updated Hero',
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()
        ->where('event', Event::Updated)
        ->where('subject_id', $companyInfo->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('hero_title_en', 'Initial Hero')
        ->and($log->new_values)->toHaveKey('hero_title_en', 'Updated Hero')
        ->and($log->new_values)->not->toHaveKey('logo');
});

test('public homepage exposes bilingual company profile payload', function () {
    CompanyInfo::create(validCompanyProfilePayload());

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyInfo.hero_title_ar', 'مرحباً بكم في أكme')
            ->where('companyInfo.hero_title_en', 'Welcome to Acme')
            ->where('companyInfo.about_ar', 'نبذة عربية عن الشركة.')
            ->where('companyInfo.about_en', 'English about content.')
            ->where('companyInfo.vision_ar', 'رؤيتنا العربية.')
            ->where('companyInfo.mission_en', 'Our English mission.')
            ->where('companyInfo.address_ar', 'الرياض، المملكة العربية السعودية')
            ->where('companyInfo.website', 'https://acme.test')
            ->where('companyInfo.facebook', 'https://facebook.com/acme')
            ->missing('companyInfo.id'));
});

test('public homepage supports bilingual fallback payload values', function () {
    CompanyInfo::create([
        'name_ar' => 'شركة',
        'name_en' => '',
        'hero_title_ar' => 'عنوان عربي',
        'hero_title_en' => '',
        'hero_description_ar' => 'وصف عربي',
        'hero_description_en' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyInfo.hero_title_ar', 'عنوان عربي')
            ->where('companyInfo.hero_title_en', '')
            ->where('companyInfo.hero_description_ar', 'وصف عربي')
            ->where('companyInfo.hero_description_en', ''));
});

test('public homepage get performs no database writes', function () {
    expect(CompanyInfo::count())->toBe(0);

    $this->get(route('home'))->assertOk();

    expect(CompanyInfo::count())->toBe(0);
});

test('homepage works when optional profile sections are empty', function () {
    CompanyInfo::create([
        'name_ar' => 'شركة',
        'name_en' => 'Company',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyInfo.about_ar', '')
            ->where('companyInfo.about_en', '')
            ->where('companyInfo.vision_ar', '')
            ->where('companyInfo.website', '')
            ->where('companyInfo.facebook', ''));
});

test('non admin cannot update company profile content', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('company-info.update'), validCompanyProfilePayload())
        ->assertRedirect(route('login'));

    expect(CompanyInfo::count())->toBe(0);
});

test('company info logo security rules remain intact', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->put(route('company-info.update'), array_merge(validCompanyProfilePayload(), [
            'logo' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('logo');
});
