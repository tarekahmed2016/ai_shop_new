<?php

use App\Models\CompanyInfo;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('guest cannot open theme colors page', function () {
    $this->get(route('theme-colors.index'))
        ->assertRedirect(route('login'));
});

test('admin can view theme colors with defaults', function () {
    $this->actingAs($this->admin)
        ->get(route('theme-colors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ThemeColors/ThemeColorsPage', false)
            ->where('themeColors.theme_primary_color', '#FFD400')
            ->where('themeColors.theme_dark_color', '#111111')
            ->where('themeColors.theme_nav_text_color', '#FFFFFF'));
});

test('admin can store theme colors', function () {
    $this->actingAs($this->admin)
        ->put(route('theme-colors.update'), [
            'theme_primary_color' => '#FF6600',
            'theme_dark_color' => '#222222',
            'theme_heading_text_color' => '#003366',
            'theme_body_text_color' => '#334455',
            'theme_muted_text_color' => '#778899',
            'theme_nav_text_color' => '#EEEEEE',
            'theme_nav_hover_text_color' => '#FFAA00',
            'theme_hero_text_color' => '#FAFAFA',
            'theme_on_dark_text_color' => '#F5F5F5',
        ])
        ->assertRedirect();

    expect(CompanyInfo::first()->theme_heading_text_color)->toBe('#003366')
        ->and(CompanyInfo::first()->theme_nav_hover_text_color)->toBe('#FFAA00');
});

test('theme colors rejects invalid hex values', function () {
    $this->actingAs($this->admin)
        ->put(route('theme-colors.update'), [
            'theme_primary_color' => 'red',
            'theme_dark_color' => '#GGGGGG',
            'theme_heading_text_color' => 'blue',
        ])
        ->assertSessionHasErrors(['theme_primary_color', 'theme_dark_color', 'theme_heading_text_color']);
});

test('public pages receive resolved theme colors in shared company info', function () {
    CompanyInfo::create([
        'name_ar' => 'شركة',
        'name_en' => 'Company',
        'theme_primary_color' => '#00AAFF',
        'theme_dark_color' => '#101010',
        'theme_heading_text_color' => '#222222',
        'theme_nav_text_color' => '#FFFFFF',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('companyInfo.theme_primary_color', '#00AAFF')
            ->where('companyInfo.theme_heading_text_color', '#222222')
            ->where('companyInfo.theme_nav_text_color', '#FFFFFF'));
});
