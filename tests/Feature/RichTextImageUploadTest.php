<?php

use App\Models\RichTextImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('guest cannot upload rich text images', function () {
    $this->post(route('rich-text-images.store'), [
        'upload' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertRedirect(route('login'));

    expect(RichTextImage::count())->toBe(0);
});

test('non admin cannot upload rich text images', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('rich-text-images.store'), [
            'upload' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertRedirect(route('login'));

    expect(RichTextImage::count())->toBe(0);
});

test('admin can upload valid jpeg png and webp rich text images', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach (['photo.jpg', 'photo.png', 'photo.webp'] as $filename) {
        $response = $this->actingAs($admin)
            ->post(route('rich-text-images.store'), [
                'upload' => UploadedFile::fake()->image($filename),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk()->assertJsonStructure(['url']);
    }

    expect(RichTextImage::count())->toBe(3);
});

test('rich text image upload rejects svg files', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)
        ->post(route('rich-text-images.store'), [
            'upload' => UploadedFile::fake()->create('icon.svg', 100, 'image/svg+xml'),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    expect($response->status())->toBe(422)
        ->and(RichTextImage::count())->toBe(0);
});

test('rich text image upload rejects php disguised as image', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)
        ->post(route('rich-text-images.store'), [
            'upload' => UploadedFile::fake()->create('shell.php.jpg', 100, 'application/x-php'),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    expect($response->status())->toBe(422)
        ->and(RichTextImage::count())->toBe(0);
});

test('rich text image upload rejects oversized files', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)
        ->post(route('rich-text-images.store'), [
            'upload' => UploadedFile::fake()->image('large.jpg')->size(6000),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    expect($response->status())->toBe(422)
        ->and(RichTextImage::count())->toBe(0);
});

test('rich text image upload stores safe unique path and returns public url', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)
        ->post(route('rich-text-images.store'), [
            'upload' => UploadedFile::fake()->image('My Photo.jpg'),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response->assertOk();

    $image = RichTextImage::first();

    expect($image)->not->toBeNull()
        ->and($image->path)->toStartWith('rich-text/')
        ->and($image->path)->not->toContain('My Photo')
        ->and($response->json('url'))->toContain('/storage/rich-text/')
        ->and(Storage::disk('public')->exists($image->path))->toBeTrue();
});

test('rich text image upload route is protected by web csrf middleware', function () {
    $route = app('router')->getRoutes()->getByName('rich-text-images.store');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('web');
});
