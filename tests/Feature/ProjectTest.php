<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\Project;
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

function validProjectPayload(array $overrides = []): array
{
    return array_merge([
        'name_ar' => 'إعادة تصميم الموقع',
        'name_en' => 'Website Redesign',
        'client_name_ar' => 'شركة أكme',
        'client_name_en' => 'Acme Corp',
        'description_ar' => 'مشروع إعادة تصميم كامل للموقع.',
        'description_en' => 'A complete website redesign project.',
        'project_date' => '2025-06-15',
        'project_url' => 'https://example.com/project',
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('project.jpg'),
    ], $overrides);
}

test('guest cannot open projects index', function () {
    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open projects index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot create a project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), validProjectPayload())
        ->assertRedirect(route('login'));

    expect(Project::count())->toBe(0);
});

test('non admin cannot update a project', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.update', $project), validProjectPayload())
        ->assertRedirect(route('login'));
});

test('non admin cannot delete a project', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('login'));

    expect(Project::find($project->id))->not->toBeNull();
});

test('non admin cannot fetch next ordering', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.next-ordering'))
        ->assertRedirect(route('login'));
});

test('admin can view projects index', function () {
    Project::factory()->create([
        'name_ar' => 'هوية العلامة',
        'name_en' => 'Brand Identity',
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/ProjectsPage', false)
            ->has('projects.data', 1)
            ->where('projects.data.0.name_ar', 'هوية العلامة')
            ->where('projects.data.0.name_en', 'Brand Identity'));
});

test('admin can create a project with arabic and english content', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_ar' => 'مشروع جديد',
            'name_en' => 'New Project',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $project = Project::where('name_en', 'New Project')->first();

    expect($project)->not->toBeNull()
        ->and($project->name_ar)->toBe('مشروع جديد')
        ->and($project->name_en)->toBe('New Project')
        ->and($project->client_name_ar)->toBe('شركة أكme')
        ->and($project->client_name_en)->toBe('Acme Corp')
        ->and($project->description_ar)->toBe('مشروع إعادة تصميم كامل للموقع.')
        ->and($project->description_en)->toBe('A complete website redesign project.')
        ->and($project->project_date?->format('Y-m-d'))->toBe('2025-06-15')
        ->and($project->project_url)->toBe('https://example.com/project')
        ->and($project->ordering)->toBe(1)
        ->and($project->is_active)->toBeTrue()
        ->and($project->attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($project->attachment->path);
});

test('creating a project requires name_ar', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload(['name_ar' => '']))
        ->assertSessionHasErrors('name_ar');

    expect(Project::count())->toBe(0);
});

test('creating a project requires name_en', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload(['name_en' => '']))
        ->assertSessionHasErrors('name_en');

    expect(Project::count())->toBe(0);
});

test('creating a project rejects name_ar exceeding max length', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_ar' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('name_ar');
});

test('creating a project rejects name_en exceeding max length', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_en' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('name_en');
});

test('creating a project validates description max length', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'description_en' => str_repeat('a', 15001),
        ]))
        ->assertSessionHasErrors('description_en');
});

test('creating a project validates client name max length', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'client_name_en' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('client_name_en');
});

test('creating a project rejects invalid date', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'project_date' => 'not-a-date',
        ]))
        ->assertSessionHasErrors('project_date');
});

test('creating a project rejects invalid url', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'project_url' => 'not-a-url',
        ]))
        ->assertSessionHasErrors('project_url');
});

test('creating a project rejects invalid ordering', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'ordering' => -1,
        ]))
        ->assertSessionHasErrors('ordering');
});

test('creating a project requires status', function () {
    $payload = validProjectPayload();
    unset($payload['is_active']);

    $this->actingAs($this->admin)
        ->post(route('projects.store'), $payload)
        ->assertSessionHasErrors('is_active');

    expect(Project::count())->toBe(0);
});

test('creating a project requires an image', function () {
    $payload = validProjectPayload();
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('projects.store'), $payload)
        ->assertSessionHasErrors('image');

    expect(Project::count())->toBe(0);
});

test('creating a project rejects svg image', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'image' => UploadedFile::fake()->create('project.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('image');

    expect(Project::count())->toBe(0);
});

test('creating a project rejects invalid image types', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('image');

    expect(Project::count())->toBe(0);
});

test('creating a project rejects oversized image', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'image' => UploadedFile::fake()->image('project.jpg')->size(5000),
        ]))
        ->assertSessionHasErrors('image');
});

test('creating a project accepts png image', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_en' => 'PNG Project',
            'image' => UploadedFile::fake()->image('project.png'),
        ]))
        ->assertRedirect();

    expect(Project::where('name_en', 'PNG Project')->exists())->toBeTrue();
});

test('admin can update arabic content', function () {
    $project = Project::factory()->create([
        'name_ar' => 'مشروع قديم',
        'name_en' => 'Old Project',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('projects.update', $project), [
            'name_ar' => 'مشروع محدث',
            'name_en' => 'Old Project',
            'client_name_ar' => $project->client_name_ar,
            'client_name_en' => $project->client_name_en,
            'description_ar' => $project->description_ar,
            'description_en' => $project->description_en,
            'project_date' => $project->project_date?->format('Y-m-d'),
            'project_url' => $project->project_url,
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($project->fresh()->name_ar)->toBe('مشروع محدث')
        ->and($project->fresh()->name_en)->toBe('Old Project');
});

test('admin can update english content', function () {
    $project = Project::factory()->create([
        'name_ar' => 'مشروع قديم',
        'name_en' => 'Old Project',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('projects.update', $project), [
            'name_ar' => 'مشروع قديم',
            'name_en' => 'Updated Project',
            'client_name_ar' => $project->client_name_ar,
            'client_name_en' => 'Updated Client',
            'description_ar' => $project->description_ar,
            'description_en' => 'Updated description',
            'project_date' => '2024-01-01',
            'project_url' => 'https://updated.test',
            'ordering' => 0,
            'is_active' => false,
        ])
        ->assertRedirect();

    $project->refresh();

    expect($project->name_en)->toBe('Updated Project')
        ->and($project->client_name_en)->toBe('Updated Client')
        ->and($project->description_en)->toBe('Updated description')
        ->and($project->project_date?->format('Y-m-d'))->toBe('2024-01-01')
        ->and($project->project_url)->toBe('https://updated.test')
        ->and($project->is_active)->toBeFalse();
});

test('admin can update a project image', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_en' => 'Image Project',
            'ordering' => 0,
        ]));

    $project = Project::where('name_en', 'Image Project')->first();
    $oldPath = $project->attachment->path;

    $this->actingAs($this->admin)
        ->post(route('projects.update', $project), [
            'name_ar' => $project->name_ar,
            'name_en' => 'Image Project',
            'client_name_ar' => $project->client_name_ar,
            'client_name_en' => $project->client_name_en,
            'description_ar' => $project->description_ar,
            'description_en' => 'Updated with image',
            'project_date' => $project->project_date?->format('Y-m-d'),
            'project_url' => $project->project_url,
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('updated.jpg'),
            '_method' => 'put',
        ])
        ->assertRedirect();

    $project->refresh();

    expect($project->attachment)->not->toBeNull()
        ->and($project->attachment->path)->not->toBe($oldPath);

    Storage::disk('public')->assertExists($project->attachment->path);
    Storage::disk('public')->assertMissing($oldPath);
});

test('admin can delete a project', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_en' => 'Delete Me',
            'ordering' => 0,
        ]));

    $project = Project::where('name_en', 'Delete Me')->first();
    $path = $project->attachment->path;

    $this->actingAs($this->admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect();

    expect(Project::find($project->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('project admin search finds arabic project name', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع فريد',
        'name_en' => 'Unique Project',
    ]);
    Project::factory()->create([
        'name_ar' => 'مشروع آخر',
        'name_en' => 'Other Project',
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['search' => 'فريد']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name_ar', 'مشروع فريد'));
});

test('project admin search finds english project name', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع فريد',
        'name_en' => 'Unique Project',
    ]);
    Project::factory()->create([
        'name_ar' => 'مشروع آخر',
        'name_en' => 'Other Project',
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name_en', 'Unique Project'));
});

test('project admin search finds arabic description', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع أ',
        'name_en' => 'Project A',
        'description_ar' => 'وصف فريد بالعربية',
    ]);
    Project::factory()->create([
        'name_ar' => 'مشروع ب',
        'name_en' => 'Project B',
        'description_ar' => 'وصف آخر',
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['search' => 'فريد']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name_en', 'Project A'));
});

test('project admin search finds english description', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع أ',
        'name_en' => 'Project A',
        'description_en' => 'Unique English description',
    ]);
    Project::factory()->create([
        'name_ar' => 'مشروع ب',
        'name_en' => 'Project B',
        'description_en' => 'Other description',
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['search' => 'Unique English']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name_en', 'Project A'));
});

test('project admin search finds client name in both languages', function () {
    Project::factory()->create([
        'name_ar' => 'مشروع أ',
        'name_en' => 'Project A',
        'client_name_ar' => 'عميل فريد',
        'client_name_en' => 'Unique Client',
    ]);
    Project::factory()->create([
        'name_ar' => 'مشروع ب',
        'name_en' => 'Project B',
        'client_name_en' => 'Other Client',
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['search' => 'Unique Client']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.client_name_en', 'Unique Client'));

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['search' => 'عميل فريد']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.client_name_ar', 'عميل فريد'));
});

test('creating a project records activity with bilingual fields', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_ar' => 'مشروع مسجل',
            'name_en' => 'Logged Project',
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new Project)->getMorphClass())
        ->and($log->new_values)->toHaveKey('name_ar', 'مشروع مسجل')
        ->and($log->new_values)->toHaveKey('name_en', 'Logged Project')
        ->and($log->new_values)->toHaveKey('client_name_en', 'Acme Corp')
        ->and($log->new_values)->not->toHaveKey('image');
});

test('updating a project records activity for changed bilingual fields', function () {
    $project = Project::factory()->create([
        'name_ar' => 'مشروع أصلي',
        'name_en' => 'Original Project',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('projects.update', $project), [
            'name_ar' => 'مشروع معاد تسميته',
            'name_en' => 'Renamed Project',
            'client_name_ar' => $project->client_name_ar,
            'client_name_en' => $project->client_name_en,
            'description_ar' => $project->description_ar,
            'description_en' => $project->description_en,
            'project_date' => $project->project_date?->format('Y-m-d'),
            'project_url' => $project->project_url,
            'ordering' => $project->ordering,
            'is_active' => false,
        ])
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Updated)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('name_en', 'Original Project')
        ->and($log->new_values)->toHaveKey('name_en', 'Renamed Project')
        ->and($log->new_values)->toHaveKey('is_active', false);
});

test('deleting a project records activity', function () {
    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_ar' => 'مشروع محذوف',
            'name_en' => 'Deleted Project',
            'ordering' => 0,
        ]));

    $project = Project::where('name_en', 'Deleted Project')->first();

    $this->actingAs($this->admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Deleted)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new Project)->getMorphClass())
        ->and($log->old_values)->toHaveKey('name_ar', 'مشروع محذوف');
});

test('admin can fetch next ordering', function () {
    Project::factory()->create(['ordering' => 3]);

    $this->actingAs($this->admin)
        ->get(route('projects.next-ordering'))
        ->assertOk()
        ->assertJson(['ordering' => 4]);
});

test('creating a project shifts existing ordering values', function () {
    $existing = Project::factory()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->post(route('projects.store'), validProjectPayload([
            'name_en' => 'Inserted Project',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    expect($existing->fresh()->ordering)->toBe(2)
        ->and(Project::where('name_en', 'Inserted Project')->first()?->ordering)->toBe(1);
});

test('deleting a project shifts down remaining ordering values', function () {
    $first = Project::factory()->create(['ordering' => 1]);
    $second = Project::factory()->create(['ordering' => 2]);

    $this->actingAs($this->admin)
        ->delete(route('projects.destroy', $first))
        ->assertRedirect();

    expect($second->fresh()->ordering)->toBe(1);
});
