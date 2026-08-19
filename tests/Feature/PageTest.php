<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    foreach (['pages.view', 'pages.create', 'pages.update', 'pages.delete'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function validPagePayload(array $overrides = []): array
{
    return array_merge([
        'title_ar' => 'الامتياز التجاري',
        'title_en' => 'Franchise',
        'menu_title_ar' => 'الامتياز',
        'menu_title_en' => 'Franchise',
        'slug' => 'franchise',
        'content_ar' => '<p>محتوى <strong>عربي</strong></p>',
        'content_en' => '<p>English <strong>content</strong></p>',
        'show_in_main_menu' => true,
        'menu_order' => 30,
        'is_active' => true,
    ], $overrides);
}

test('guest cannot open pages index', function () {
    $this->get(route('pages.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot manage pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pages.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->post(route('pages.store'), validPagePayload())
        ->assertRedirect(route('login'));

    expect(Page::count())->toBe(0);
});

test('admin can view pages index', function () {
    Page::factory()->create(['title_en' => 'About Us']);

    $this->actingAs($this->admin)
        ->get(route('pages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pages/PagesPage', false)
            ->has('pages.data', 1));
});

test('admin can create page with sanitized rich content stored in database', function () {
    $this->actingAs($this->admin)
        ->post(route('pages.store'), validPagePayload([
            'content_ar' => '<p>Hello</p><script>alert(1)</script>',
            'content_en' => '<p onclick="alert(1)">Hello</p>',
        ]))
        ->assertRedirect();

    $page = Page::first();

    expect($page)->not->toBeNull()
        ->and($page->content_ar)->not->toContain('<script>')
        ->and($page->content_en)->not->toContain('onclick')
        ->and($page->content_ar)->toContain('Hello');
});

test('duplicate slug is rejected', function () {
    Page::factory()->create(['slug' => 'franchise']);

    $this->actingAs($this->admin)
        ->post(route('pages.store'), validPagePayload(['slug' => 'franchise']))
        ->assertSessionHasErrors('slug');
});

test('invalid slug is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('pages.store'), validPagePayload(['slug' => '../bad slug']))
        ->assertSessionHasErrors('slug');
});

test('inactive page returns 404 publicly', function () {
    Page::factory()->create([
        'slug' => 'hidden-page',
        'is_active' => false,
    ]);

    $this->get(route('public.page.show', ['slug' => 'hidden-page']))
        ->assertNotFound();
});

test('active page is publicly accessible', function () {
    Page::factory()->create([
        'slug' => 'franchise',
        'title_en' => 'Franchise',
        'content_en' => '<p>Franchise content</p>',
        'is_active' => true,
    ]);

    $this->get(route('public.page.show', ['slug' => 'franchise']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/CustomPage', false)
            ->where('page.slug', 'franchise'));
});

test('menu pages shared only when active and visible in main menu', function () {
    Page::factory()->create([
        'slug' => 'visible',
        'show_in_main_menu' => true,
        'is_active' => true,
        'menu_order' => 30,
    ]);

    Page::factory()->create([
        'slug' => 'hidden-menu',
        'show_in_main_menu' => false,
        'is_active' => true,
    ]);

    Page::factory()->create([
        'slug' => 'inactive-menu',
        'show_in_main_menu' => true,
        'is_active' => false,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('menuPages', 1)
            ->where('menuPages.0.slug', 'visible'));
});

test('page create update delete are activity logged without rich html content', function () {
    $this->actingAs($this->admin)
        ->post(route('pages.store'), validPagePayload())
        ->assertRedirect();

    $page = Page::first();

    expect(ActivityLog::where('event', Event::Created)->where('subject_id', $page->id)->exists())->toBeTrue();

    $log = ActivityLog::where('event', Event::Created)->where('subject_id', $page->id)->first();
    expect(json_encode($log->properties))->not->toContain('<strong>');

    $this->actingAs($this->admin)
        ->put(route('pages.update', ['page' => $page]), validPagePayload([
            'menu_order' => 35,
            'content_ar' => '<p>Updated</p>',
        ]))
        ->assertRedirect();

    expect(ActivityLog::where('event', Event::Updated)->where('subject_id', $page->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->delete(route('pages.destroy', ['page' => $page]))
        ->assertRedirect();

    expect(Page::count())->toBe(0)
        ->and(ActivityLog::where('event', Event::Deleted)->where('subject_id', $page->id)->exists())->toBeTrue();
});

test('safe rich html formatting is preserved in stored page content', function () {
    $html = '<p>Hello <strong>world</strong></p><ul><li>One</li></ul><a href="https://example.org">Safe</a>';

    $this->actingAs($this->admin)
        ->post(route('pages.store'), validPagePayload(['content_en' => $html]))
        ->assertRedirect();

    expect(Page::first()->content_en)
        ->toContain('<strong>world</strong>')
        ->toContain('<li>One</li>')
        ->toContain('https://example.org');
});
