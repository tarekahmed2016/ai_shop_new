<?php

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Support\RichTextSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('server removes script tags from stored rich html', function () {
    $this->actingAs($this->admin)
        ->post(route('pages.store'), [
            'title_ar' => 'صفحة',
            'title_en' => 'Page',
            'slug' => 'secure-page',
            'content_ar' => '<p>Hello</p><script>alert(1)</script>',
            'content_en' => '<p>Safe</p>',
            'show_in_main_menu' => false,
            'menu_order' => 100,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Page::first()->content_ar)->not->toContain('<script>')
        ->and(Page::first()->content_ar)->toContain('Hello');
});

test('server removes image event handlers from stored rich html', function () {
    $html = '<figure class="image"><img src="/storage/rich-text/2026/08/test.jpg" alt="x" onerror="alert(1)"></figure>';

    $this->actingAs($this->admin)
        ->post(route('pages.store'), [
            'title_ar' => 'صفحة',
            'title_en' => 'Page',
            'slug' => 'image-event-page',
            'content_ar' => $html,
            'content_en' => '<p>Safe</p>',
            'show_in_main_menu' => false,
            'menu_order' => 100,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Page::first()->content_ar)->not->toContain('onerror');
});

test('server removes svg onload payloads from stored rich html', function () {
    $html = '<svg onload="alert(1)"></svg><p>Text</p>';

    $stored = RichTextSanitizer::sanitize($html);

    expect($stored)->not->toContain('onload')
        ->and($stored)->not->toContain('<svg')
        ->and($stored)->toContain('Text');
});

test('server removes javascript urls from stored rich html', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name_ar' => 'مشروع',
            'name_en' => 'Project',
            'description_ar' => '<a href="javascript:alert(1)">Bad</a>',
            'description_en' => '<a href="https://example.org">Safe</a>',
            'ordering' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('project.jpg'),
        ])
        ->assertRedirect();

    $project = Project::first();

    expect($project->description_ar)->not->toContain('javascript:')
        ->and($project->description_en)->toContain('https://example.org');
});

test('server removes dangerous css url constructs from stored rich html', function () {
    $html = '<p style="background-image:url(javascript:alert(1))">Bad</p>';

    $stored = RichTextSanitizer::sanitize($html);

    expect($stored)->not->toContain('javascript:');
});

test('server preserves legitimate figure table and formatting markup in database', function () {
    $html = <<<'HTML'
<p><span style="color:#FFD400;">Colored</span></p>
<h2>Section</h2>
<ul><li>One</li></ul>
<figure class="image"><img src="/storage/rich-text/2026/08/sample.jpg" alt="Sample"><figcaption>Caption</figcaption></figure>
<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>
HTML;

    $this->actingAs($this->admin)
        ->post(route('pages.store'), [
            'title_ar' => 'صفحة',
            'title_en' => 'Page',
            'slug' => 'rich-format-page',
            'content_ar' => $html,
            'content_en' => '<p><strong>English</strong></p>',
            'show_in_main_menu' => false,
            'menu_order' => 100,
            'is_active' => true,
        ])
        ->assertRedirect();

    $stored = Page::first()->content_ar;

    expect($stored)->toContain('<h2>Section</h2>')
        ->and($stored)->toContain('<li>One</li>')
        ->and($stored)->toContain('<img')
        ->and($stored)->toContain('alt="Sample"')
        ->and($stored)->toContain('<figcaption>Caption</figcaption>')
        ->and($stored)->toContain('<table>')
        ->and($stored)->toContain('<td>Cell</td>');
});

test('server strips remote and data image sources from stored rich html', function () {
    $html = '<p><img src="https://evil.example/image.jpg" alt="bad"><img src="data:image/png;base64,abc" alt="data"></p>';

    $stored = RichTextSanitizer::sanitize($html);

    expect($stored)->not->toContain('evil.example')
        ->and($stored)->not->toContain('data:image');
});
