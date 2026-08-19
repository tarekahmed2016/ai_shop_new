<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('server sanitizes malicious script tags before storing project description', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name_ar' => 'مشروع',
            'name_en' => 'Project',
            'description_ar' => '<p>Hello</p><script>alert(1)</script>',
            'description_en' => '<p>Safe</p>',
            'ordering' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('project.jpg'),
        ])
        ->assertRedirect();

    $project = Project::first();

    expect($project->description_ar)->not->toContain('<script>')
        ->and($project->description_ar)->toContain('Hello');
});

test('server sanitizes event handlers before storing project description', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name_ar' => 'مشروع',
            'name_en' => 'Project',
            'description_ar' => '<p>Hello</p>',
            'description_en' => '<p onclick="alert(1)">Hello</p>',
            'ordering' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('project.jpg'),
        ])
        ->assertRedirect();

    expect(Project::first()->description_en)->not->toContain('onclick');
});

test('server sanitizes javascript urls before storing project description', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name_ar' => 'مشروع',
            'name_en' => 'Project',
            'description_ar' => '<a href="javascript:alert(1)">Bad</a>',
            'description_en' => '<a href="https://example.org">Safe link</a>',
            'ordering' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('project.jpg'),
        ])
        ->assertRedirect();

    $stored = Project::first();

    expect($stored->description_ar)->not->toContain('javascript:')
        ->and($stored->description_en)->toContain('https://example.org');
});

test('server preserves safe rich text formatting in stored project description', function () {
    $html = '<p>Hello <strong>world</strong></p><ul><li>One</li><li>Two</li></ul>';

    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name_ar' => 'مشروع',
            'name_en' => 'Project',
            'description_ar' => $html,
            'description_en' => '<p><em>Text</em></p>',
            'ordering' => 1,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('project.jpg'),
        ])
        ->assertRedirect();

    $stored = Project::first();

    expect($stored->description_ar)->toContain('<strong>world</strong>')
        ->and($stored->description_ar)->toContain('<li>One</li>')
        ->and($stored->description_en)->toContain('<em>Text</em>');
});
