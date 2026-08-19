<?php

use App\Models\CompanyInfo;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('guest cannot open custom assets page', function () {
    $this->get(route('custom-assets.index'))
        ->assertRedirect(route('login'));
});

test('admin can view custom assets with empty defaults', function () {
    $this->actingAs($this->admin)
        ->get(route('custom-assets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CustomAssets/CustomAssetsPage', false)
            ->where('customAssets.custom_css', '')
            ->where('customAssets.custom_js', ''));
});

test('admin can store custom css and js', function () {
    $css = '.public-layout .test { color: red; }';
    $js = "console.log('custom');";

    $this->actingAs($this->admin)
        ->put(route('custom-assets.update'), [
            'custom_css' => $css,
            'custom_js' => $js,
        ])
        ->assertRedirect();

    expect(CompanyInfo::first()->custom_css)->toBe($css)
        ->and(CompanyInfo::first()->custom_js)->toBe($js);
});

test('public pages receive custom assets in shared company info', function () {
    CompanyInfo::create([
        'name_ar' => 'شركة',
        'name_en' => 'Company',
        'custom_css' => '.public-layout .x { display: none; }',
        'custom_js' => 'window.__custom = true;',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('companyInfo.custom_css', '.public-layout .x { display: none; }')
            ->where('companyInfo.custom_js', 'window.__custom = true;'));
});

test('custom assets rejects oversized css', function () {
    $this->actingAs($this->admin)
        ->put(route('custom-assets.update'), [
            'custom_css' => str_repeat('a', 50001),
        ])
        ->assertSessionHasErrors('custom_css');
});
