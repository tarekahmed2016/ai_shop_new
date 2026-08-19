<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\ClientPartnerType;
use App\Models\ActivityLog;
use App\Models\ClientPartner;
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

function validClientPartnerPayload(array $overrides = []): array
{
    return array_merge([
        'type' => ClientPartnerType::Client->value,
        'name_ar' => 'شركة الطارق للتقنية',
        'name_en' => 'Al-Tarek Technology',
        'website' => 'https://altarek.example.com',
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('logo.jpg'),
    ], $overrides);
}

test('guest cannot open clients partners index', function () {
    $this->get(route('clients-partners.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open clients partners index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clients-partners.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot create a client partner record', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clients-partners.store'), validClientPartnerPayload())
        ->assertRedirect(route('login'));

    expect(ClientPartner::count())->toBe(0);
});

test('non admin cannot update a client partner record', function () {
    $record = ClientPartner::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('clients-partners.update', $record), validClientPartnerPayload())
        ->assertRedirect(route('login'));
});

test('non admin cannot delete a client partner record', function () {
    $record = ClientPartner::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('clients-partners.destroy', $record))
        ->assertRedirect(route('login'));

    expect(ClientPartner::find($record->id))->not->toBeNull();
});

test('non admin cannot fetch next ordering', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clients-partners.next-ordering', ['type' => ClientPartnerType::Client->value]))
        ->assertRedirect(route('login'));
});

test('admin can view clients partners index', function () {
    ClientPartner::factory()->client()->create([
        'name_ar' => 'عميل أ',
        'name_en' => 'Client A',
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ClientsPartners/ClientsPartnersPage', false)
            ->has('clientPartners.data', 1)
            ->where('clientPartners.data.0.name_ar', 'عميل أ')
            ->where('clientPartners.data.0.name_en', 'Client A'));
});

test('admin can create a client', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_ar' => 'عميل جديد',
            'name_en' => 'New Client',
            'type' => ClientPartnerType::Client->value,
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $record = ClientPartner::where('name_en', 'New Client')->first();

    expect($record)->not->toBeNull()
        ->and($record->type)->toBe(ClientPartnerType::Client)
        ->and($record->name_ar)->toBe('عميل جديد')
        ->and($record->name_en)->toBe('New Client')
        ->and($record->website)->toBe('https://altarek.example.com')
        ->and($record->ordering)->toBe(1)
        ->and($record->is_active)->toBeTrue()
        ->and($record->attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($record->attachment->path);
});

test('admin can create a partner', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_ar' => 'شريك جديد',
            'name_en' => 'New Partner',
            'type' => ClientPartnerType::Partner->value,
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $record = ClientPartner::where('name_en', 'New Partner')->first();

    expect($record)->not->toBeNull()
        ->and($record->type)->toBe(ClientPartnerType::Partner);
});

test('creating requires type', function () {
    $payload = validClientPartnerPayload();
    unset($payload['type']);

    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), $payload)
        ->assertSessionHasErrors('type');
});

test('creating rejects invalid type', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'type' => 'invalid',
        ]))
        ->assertSessionHasErrors('type');
});

test('creating requires name_ar', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload(['name_ar' => '']))
        ->assertSessionHasErrors('name_ar');
});

test('creating requires name_en', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload(['name_en' => '']))
        ->assertSessionHasErrors('name_en');
});

test('creating rejects invalid website', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'website' => 'not-a-url',
        ]))
        ->assertSessionHasErrors('website');
});

test('creating rejects invalid ordering', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'ordering' => -1,
        ]))
        ->assertSessionHasErrors('ordering');
});

test('creating requires status', function () {
    $payload = validClientPartnerPayload();
    unset($payload['is_active']);

    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), $payload)
        ->assertSessionHasErrors('is_active');
});

test('creating requires logo on create', function () {
    $payload = validClientPartnerPayload();
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), $payload)
        ->assertSessionHasErrors('image');
});

test('creating rejects svg logo', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'image' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('image');
});

test('creating rejects oversized logo', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'image' => UploadedFile::fake()->image('logo.jpg')->size(5000),
        ]))
        ->assertSessionHasErrors('image');
});

test('creating accepts png logo', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_en' => 'PNG Client',
            'image' => UploadedFile::fake()->image('logo.png'),
        ]))
        ->assertRedirect();

    expect(ClientPartner::where('name_en', 'PNG Client')->exists())->toBeTrue();
});

test('admin can update arabic and english names', function () {
    $record = ClientPartner::factory()->client()->create([
        'name_ar' => 'اسم قديم',
        'name_en' => 'Old Name',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('clients-partners.update', $record), [
            'type' => ClientPartnerType::Client->value,
            'name_ar' => 'اسم محدث',
            'name_en' => 'Updated Name',
            'website' => $record->website,
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->name_ar)->toBe('اسم محدث')
        ->and($record->name_en)->toBe('Updated Name');
});

test('admin can update logo', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_en' => 'Logo Client',
            'ordering' => 0,
        ]));

    $record = ClientPartner::where('name_en', 'Logo Client')->first();
    $oldPath = $record->attachment->path;

    $this->actingAs($this->admin)
        ->post(route('clients-partners.update', $record), [
            'type' => ClientPartnerType::Client->value,
            'name_ar' => $record->name_ar,
            'name_en' => 'Logo Client',
            'website' => $record->website,
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('updated.jpg'),
            '_method' => 'put',
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->attachment->path)->not->toBe($oldPath);
    Storage::disk('public')->assertExists($record->attachment->path);
    Storage::disk('public')->assertMissing($oldPath);
});

test('admin can delete a client partner record', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_en' => 'Delete Me',
            'ordering' => 0,
        ]));

    $record = ClientPartner::where('name_en', 'Delete Me')->first();
    $path = $record->attachment->path;

    $this->actingAs($this->admin)
        ->delete(route('clients-partners.destroy', $record))
        ->assertRedirect();

    expect(ClientPartner::find($record->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('clients partners search finds arabic name', function () {
    ClientPartner::factory()->client()->create([
        'name_ar' => 'عميل فريد',
        'name_en' => 'Unique Client',
    ]);
    ClientPartner::factory()->client()->create([
        'name_ar' => 'عميل آخر',
        'name_en' => 'Other Client',
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index', ['search' => 'فريد']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientPartners.data', 1)
            ->where('clientPartners.data.0.name_ar', 'عميل فريد'));
});

test('clients partners search finds english name', function () {
    ClientPartner::factory()->client()->create([
        'name_ar' => 'عميل فريد',
        'name_en' => 'Unique Client',
    ]);
    ClientPartner::factory()->client()->create([
        'name_ar' => 'عميل آخر',
        'name_en' => 'Other Client',
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientPartners.data', 1)
            ->where('clientPartners.data.0.name_en', 'Unique Client'));
});

test('clients partners search finds website', function () {
    ClientPartner::factory()->client()->create([
        'name_en' => 'Client A',
        'website' => 'https://unique-client.test',
    ]);
    ClientPartner::factory()->client()->create([
        'name_en' => 'Client B',
        'website' => 'https://other-client.test',
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index', ['search' => 'unique-client']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientPartners.data', 1)
            ->where('clientPartners.data.0.website', 'https://unique-client.test'));
});

test('clients partners index filters clients only', function () {
    ClientPartner::factory()->client()->create(['name_en' => 'Visible Client']);
    ClientPartner::factory()->partner()->create(['name_en' => 'Hidden Partner']);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index', ['type' => 'client']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientPartners.data', 1)
            ->where('clientPartners.data.0.name_en', 'Visible Client')
            ->where('filters.type', 'client'));
});

test('clients partners index filters partners only', function () {
    ClientPartner::factory()->client()->create(['name_en' => 'Hidden Client']);
    ClientPartner::factory()->partner()->create(['name_en' => 'Visible Partner']);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index', ['type' => 'partner']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientPartners.data', 1)
            ->where('clientPartners.data.0.name_en', 'Visible Partner')
            ->where('filters.type', 'partner'));
});

test('clients partners index ignores invalid type filter', function () {
    ClientPartner::factory()->client()->create(['name_en' => 'Client A']);
    ClientPartner::factory()->partner()->create(['name_en' => 'Partner A']);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.index', ['type' => 'invalid']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientPartners.data', 2)
            ->where('filters.type', 'all'));
});

test('next ordering requires valid type', function () {
    $this->actingAs($this->admin)
        ->get(route('clients-partners.next-ordering'))
        ->assertSessionHasErrors('type');
});

test('admin can fetch next client ordering', function () {
    ClientPartner::factory()->client()->create(['ordering' => 3]);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.next-ordering', ['type' => ClientPartnerType::Client->value]))
        ->assertOk()
        ->assertJson(['ordering' => 4]);
});

test('admin can fetch next partner ordering', function () {
    ClientPartner::factory()->partner()->create(['ordering' => 2]);

    $this->actingAs($this->admin)
        ->get(route('clients-partners.next-ordering', ['type' => ClientPartnerType::Partner->value]))
        ->assertOk()
        ->assertJson(['ordering' => 3]);
});

test('client ordering shifts do not affect partners', function () {
    $client = ClientPartner::factory()->client()->create(['ordering' => 1]);
    $partner = ClientPartner::factory()->partner()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'type' => ClientPartnerType::Client->value,
            'name_en' => 'Inserted Client',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    expect($client->fresh()->ordering)->toBe(2)
        ->and($partner->fresh()->ordering)->toBe(1)
        ->and(ClientPartner::where('name_en', 'Inserted Client')->first()?->ordering)->toBe(1);
});

test('partner ordering shifts do not affect clients', function () {
    $client = ClientPartner::factory()->client()->create(['ordering' => 1]);
    $partner = ClientPartner::factory()->partner()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'type' => ClientPartnerType::Partner->value,
            'name_en' => 'Inserted Partner',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    expect($partner->fresh()->ordering)->toBe(2)
        ->and($client->fresh()->ordering)->toBe(1)
        ->and(ClientPartner::where('name_en', 'Inserted Partner')->first()?->ordering)->toBe(1);
});

test('deleting a client shifts only client ordering', function () {
    $firstClient = ClientPartner::factory()->client()->create(['ordering' => 1]);
    $secondClient = ClientPartner::factory()->client()->create(['ordering' => 2]);
    $partner = ClientPartner::factory()->partner()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->delete(route('clients-partners.destroy', $firstClient))
        ->assertRedirect();

    expect($secondClient->fresh()->ordering)->toBe(1)
        ->and($partner->fresh()->ordering)->toBe(1);
});

test('creating a client records activity with bilingual fields', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_ar' => 'عميل مسجل',
            'name_en' => 'Logged Client',
            'type' => ClientPartnerType::Client->value,
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new ClientPartner)->getMorphClass())
        ->and($log->new_values)->toHaveKey('name_ar', 'عميل مسجل')
        ->and($log->new_values)->toHaveKey('name_en', 'Logged Client')
        ->and($log->new_values)->toHaveKey('type', ClientPartnerType::Client->value)
        ->and($log->new_values)->not->toHaveKey('image');
});

test('creating a partner records activity with type', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_en' => 'Logged Partner',
            'type' => ClientPartnerType::Partner->value,
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log->new_values)->toHaveKey('type', ClientPartnerType::Partner->value);
});

test('deleting a client partner records activity', function () {
    $this->actingAs($this->admin)
        ->post(route('clients-partners.store'), validClientPartnerPayload([
            'name_ar' => 'سجل محذوف',
            'name_en' => 'Deleted Record',
            'ordering' => 0,
        ]));

    $record = ClientPartner::where('name_en', 'Deleted Record')->first();

    $this->actingAs($this->admin)
        ->delete(route('clients-partners.destroy', $record))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Deleted)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('name_ar', 'سجل محذوف');
});

test('homepage shows only active clients in ordering', function () {
    Storage::fake('public');

    $first = ClientPartner::factory()->client()->create([
        'name_ar' => 'العميل الأول',
        'name_en' => 'First Client',
        'ordering' => 1,
        'is_active' => true,
    ]);
    ClientPartner::factory()->client()->create([
        'name_ar' => 'العميل الثاني',
        'name_en' => 'Second Client',
        'ordering' => 2,
        'is_active' => true,
    ]);
    ClientPartner::factory()->client()->inactive()->create([
        'name_en' => 'Hidden Client',
        'ordering' => 0,
    ]);

    $firstPath = UploadedFile::fake()->image('first.jpg')->store('clients-partners', 'public');
    $first->attachment()->create(['name' => 'first.jpg', 'path' => $firstPath]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients', 2)
            ->where('clients.0.name_en', 'First Client')
            ->where('clients.1.name_en', 'Second Client')
            ->where('clients.0.logo', asset('storage/'.$firstPath))
            ->missing('clients.0.id')
            ->missing('clients.0.ordering')
            ->missing('clients.0.is_active')
            ->missing('clients.0.type'));
});

test('homepage shows only active partners in ordering', function () {
    ClientPartner::factory()->partner()->create([
        'name_en' => 'First Partner',
        'ordering' => 1,
        'is_active' => true,
    ]);
    ClientPartner::factory()->partner()->inactive()->create([
        'name_en' => 'Hidden Partner',
        'ordering' => 0,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('partners', 1)
            ->where('partners.0.name_en', 'First Partner')
            ->missing('partners.0.id')
            ->missing('partners.0.type'));
});

test('homepage works with zero clients and partners', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients', [])
            ->where('partners', []));
});

test('homepage public client partner payload excludes admin-only fields', function () {
    ClientPartner::factory()->client()->create([
        'name_ar' => 'عميل عام',
        'name_en' => 'Public Client',
        'website' => 'https://client.test',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.0.name_ar', 'عميل عام')
            ->where('clients.0.name_en', 'Public Client')
            ->where('clients.0.website', 'https://client.test')
            ->missing('clients.0.is_active')
            ->missing('clients.0.ordering')
            ->missing('clients.0.id')
            ->missing('clients.0.created_at')
            ->missing('clients.0.updated_at')
            ->missing('clients.0.type'));
});
