<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
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

function validServicePayload(array $overrides = []): array
{
    return array_merge([
        'name_ar' => 'خدمة استشارية',
        'name_en' => 'Consulting Service',
        'description_ar' => 'دعم استشاري احترافي.',
        'description_en' => 'Professional consulting support.',
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('service.jpg'),
    ], $overrides);
}

test('guest cannot open services index', function () {
    $this->get(route('services.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open services index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('services.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot create a service', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('services.store'), validServicePayload())
        ->assertRedirect(route('login'));

    expect(Service::count())->toBe(0);
});

test('admin can view services index', function () {
    Service::factory()->create([
        'name_ar' => 'خدمة التصميم',
        'name_en' => 'Design Service',
    ]);

    $this->actingAs($this->admin)
        ->get(route('services.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Services/ServicesPage', false)
            ->has('services.data', 1)
            ->where('services.data.0.name_ar', 'خدمة التصميم')
            ->where('services.data.0.name_en', 'Design Service'));
});

test('admin can create a service with arabic and english content', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload([
            'name_ar' => 'خدمة جديدة',
            'name_en' => 'New Service',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $service = Service::where('name_en', 'New Service')->first();

    expect($service)->not->toBeNull()
        ->and($service->name_ar)->toBe('خدمة جديدة')
        ->and($service->name_en)->toBe('New Service')
        ->and($service->description_ar)->toBe('دعم استشاري احترافي.')
        ->and($service->description_en)->toBe('Professional consulting support.')
        ->and($service->ordering)->toBe(1)
        ->and($service->is_active)->toBeTrue()
        ->and($service->attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($service->attachment->path);
});

test('creating a service requires name_ar', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload(['name_ar' => '']))
        ->assertSessionHasErrors('name_ar');

    expect(Service::count())->toBe(0);
});

test('creating a service requires name_en', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload(['name_en' => '']))
        ->assertSessionHasErrors('name_en');

    expect(Service::count())->toBe(0);
});

test('creating a service validates description max length', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload([
            'description_en' => str_repeat('a', 15001),
        ]))
        ->assertSessionHasErrors('description_en');
});

test('creating a service requires an image', function () {
    $payload = validServicePayload();
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('services.store'), $payload)
        ->assertSessionHasErrors('image');

    expect(Service::count())->toBe(0);
});

test('creating a service rejects svg image', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload([
            'image' => UploadedFile::fake()->create('service.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('image');

    expect(Service::count())->toBe(0);
});

test('admin can update arabic content', function () {
    $service = Service::factory()->create([
        'name_ar' => 'خدمة قديمة',
        'name_en' => 'Old Service',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('services.update', $service), [
            'name_ar' => 'خدمة محدثة',
            'name_en' => 'Old Service',
            'description_ar' => $service->description_ar,
            'description_en' => $service->description_en,
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($service->fresh()->name_ar)->toBe('خدمة محدثة')
        ->and($service->fresh()->name_en)->toBe('Old Service');
});

test('admin can update english content', function () {
    $service = Service::factory()->create([
        'name_ar' => 'خدمة قديمة',
        'name_en' => 'Old Service',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('services.update', $service), [
            'name_ar' => 'خدمة قديمة',
            'name_en' => 'Updated Service',
            'description_ar' => $service->description_ar,
            'description_en' => 'Updated description',
            'ordering' => 0,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($service->fresh()->name_en)->toBe('Updated Service')
        ->and($service->fresh()->description_en)->toBe('Updated description')
        ->and($service->fresh()->is_active)->toBeFalse();
});

test('admin can update a service image', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload([
            'name_en' => 'Image Service',
            'ordering' => 0,
        ]));

    $service = Service::where('name_en', 'Image Service')->first();
    $oldPath = $service->attachment->path;

    $this->actingAs($this->admin)
        ->post(route('services.update', $service), [
            'name_ar' => $service->name_ar,
            'name_en' => 'Image Service',
            'description_ar' => $service->description_ar,
            'description_en' => 'Updated with image',
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('updated.jpg'),
            '_method' => 'put',
        ])
        ->assertRedirect();

    $service->refresh();

    expect($service->attachment)->not->toBeNull()
        ->and($service->attachment->path)->not->toBe($oldPath);

    Storage::disk('public')->assertExists($service->attachment->path);
    Storage::disk('public')->assertMissing($oldPath);
});

test('admin can delete a service', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload([
            'name_en' => 'Delete Me',
            'ordering' => 0,
        ]));

    $service = Service::where('name_en', 'Delete Me')->first();
    $path = $service->attachment->path;

    $this->actingAs($this->admin)
        ->delete(route('services.destroy', $service))
        ->assertRedirect();

    expect(Service::find($service->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('service admin search finds arabic content', function () {
    Service::factory()->create([
        'name_ar' => 'خدمة فريدة',
        'name_en' => 'Unique Service',
    ]);
    Service::factory()->create([
        'name_ar' => 'خدمة أخرى',
        'name_en' => 'Other Service',
    ]);

    $this->actingAs($this->admin)
        ->get(route('services.index', ['search' => 'فريدة']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.name_ar', 'خدمة فريدة'));
});

test('service admin search finds english content', function () {
    Service::factory()->create([
        'name_ar' => 'خدمة فريدة',
        'name_en' => 'Unique Service',
    ]);
    Service::factory()->create([
        'name_ar' => 'خدمة أخرى',
        'name_en' => 'Other Service',
    ]);

    $this->actingAs($this->admin)
        ->get(route('services.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.name_en', 'Unique Service'));
});

test('creating a service records activity with bilingual fields', function () {
    $this->actingAs($this->admin)
        ->post(route('services.store'), validServicePayload([
            'name_ar' => 'خدمة مسجلة',
            'name_en' => 'Logged Service',
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new Service)->getMorphClass())
        ->and($log->new_values)->toHaveKey('name_ar', 'خدمة مسجلة')
        ->and($log->new_values)->toHaveKey('name_en', 'Logged Service')
        ->and($log->new_values)->not->toHaveKey('image');
});

test('updating a service records activity for changed bilingual fields', function () {
    $service = Service::factory()->create([
        'name_ar' => 'خدمة أصلية',
        'name_en' => 'Original Service',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('services.update', $service), [
            'name_ar' => 'خدمة معاد تسميتها',
            'name_en' => 'Renamed Service',
            'description_ar' => $service->description_ar,
            'description_en' => $service->description_en,
            'ordering' => $service->ordering,
            'is_active' => false,
        ])
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Updated)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('name_en', 'Original Service')
        ->and($log->new_values)->toHaveKey('name_en', 'Renamed Service')
        ->and($log->new_values)->toHaveKey('is_active', false);
});

test('admin can fetch next ordering', function () {
    Service::factory()->create(['ordering' => 3]);

    $this->actingAs($this->admin)
        ->get(route('services.next-ordering'))
        ->assertOk()
        ->assertJson(['ordering' => 4]);
});
