<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\CertificateAwardType;
use App\Models\ActivityLog;
use App\Models\CertificateAward;
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

function validCertificateAwardPayload(array $overrides = []): array
{
    return array_merge([
        'type' => CertificateAwardType::Certificate->value,
        'title_ar' => 'شهادة الجودة',
        'title_en' => 'Quality Certificate',
        'issuer_ar' => 'جهة الاعتماد',
        'issuer_en' => 'Accreditation Body',
        'description_ar' => 'وصف الشهادة بالعربية',
        'description_en' => 'Certificate description in English',
        'issued_date' => '2024-06-15',
        'external_url' => 'https://verify.example.com/cert',
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('certificate.jpg'),
    ], $overrides);
}

test('guest cannot open certificates awards index', function () {
    $this->get(route('certificates-awards.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open certificates awards index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('certificates-awards.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot create a certificate award record', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload())
        ->assertRedirect(route('login'));

    expect(CertificateAward::count())->toBe(0);
});

test('non admin cannot update a certificate award record', function () {
    $record = CertificateAward::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('certificates-awards.update', $record), validCertificateAwardPayload())
        ->assertRedirect(route('login'));
});

test('non admin cannot delete a certificate award record', function () {
    $record = CertificateAward::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('certificates-awards.destroy', $record))
        ->assertRedirect(route('login'));

    expect(CertificateAward::find($record->id))->not->toBeNull();
});

test('non admin cannot fetch next ordering', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('certificates-awards.next-ordering', ['type' => CertificateAwardType::Certificate->value]))
        ->assertRedirect(route('login'));
});

test('admin can view certificates awards index', function () {
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'شهادة أ',
        'title_en' => 'Certificate A',
    ]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CertificatesAwards/CertificatesAwardsPage', false)
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.title_ar', 'شهادة أ')
            ->where('certificateAwards.data.0.title_en', 'Certificate A'));
});

test('admin can create a certificate', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_ar' => 'شهادة جديدة',
            'title_en' => 'New Certificate',
            'type' => CertificateAwardType::Certificate->value,
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $record = CertificateAward::where('title_en', 'New Certificate')->first();

    expect($record)->not->toBeNull()
        ->and($record->type)->toBe(CertificateAwardType::Certificate)
        ->and($record->title_ar)->toBe('شهادة جديدة')
        ->and($record->title_en)->toBe('New Certificate')
        ->and($record->issuer_ar)->toBe('جهة الاعتماد')
        ->and($record->issuer_en)->toBe('Accreditation Body')
        ->and($record->description_ar)->toBe('وصف الشهادة بالعربية')
        ->and($record->description_en)->toBe('Certificate description in English')
        ->and($record->issued_date?->format('Y-m-d'))->toBe('2024-06-15')
        ->and($record->external_url)->toBe('https://verify.example.com/cert')
        ->and($record->ordering)->toBe(1)
        ->and($record->is_active)->toBeTrue()
        ->and($record->attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($record->attachment->path);
});

test('admin can create an award', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_ar' => 'جائزة جديدة',
            'title_en' => 'New Award',
            'type' => CertificateAwardType::Award->value,
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $record = CertificateAward::where('title_en', 'New Award')->first();

    expect($record)->not->toBeNull()
        ->and($record->type)->toBe(CertificateAwardType::Award);
});

test('creating requires type', function () {
    $payload = validCertificateAwardPayload();
    unset($payload['type']);

    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), $payload)
        ->assertSessionHasErrors('type');
});

test('creating rejects invalid type', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'type' => 'invalid',
        ]))
        ->assertSessionHasErrors('type');
});

test('creating requires title_ar', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload(['title_ar' => '']))
        ->assertSessionHasErrors('title_ar');
});

test('creating requires title_en', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload(['title_en' => '']))
        ->assertSessionHasErrors('title_en');
});

test('creating rejects title max length', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_en' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('title_en');
});

test('creating rejects description max length', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'description_en' => str_repeat('a', 15001),
        ]))
        ->assertSessionHasErrors('description_en');
});

test('creating rejects invalid issued date', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'issued_date' => 'not-a-date',
        ]))
        ->assertSessionHasErrors('issued_date');
});

test('creating rejects invalid external url', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'external_url' => 'not-a-url',
        ]))
        ->assertSessionHasErrors('external_url');
});

test('creating rejects invalid ordering', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'ordering' => -1,
        ]))
        ->assertSessionHasErrors('ordering');
});

test('creating requires status', function () {
    $payload = validCertificateAwardPayload();
    unset($payload['is_active']);

    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), $payload)
        ->assertSessionHasErrors('is_active');
});

test('creating requires image on create', function () {
    $payload = validCertificateAwardPayload();
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), $payload)
        ->assertSessionHasErrors('image');
});

test('creating rejects svg image', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'image' => UploadedFile::fake()->create('certificate.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('image');
});

test('creating rejects oversized image', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'image' => UploadedFile::fake()->image('certificate.jpg')->size(5000),
        ]))
        ->assertSessionHasErrors('image');
});

test('creating accepts png image', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_en' => 'PNG Certificate',
            'image' => UploadedFile::fake()->image('certificate.png'),
        ]))
        ->assertRedirect();

    expect(CertificateAward::where('title_en', 'PNG Certificate')->exists())->toBeTrue();
});

test('admin can update bilingual fields', function () {
    $record = CertificateAward::factory()->certificate()->create([
        'title_ar' => 'عنوان قديم',
        'title_en' => 'Old Title',
        'issuer_ar' => 'جهة قديمة',
        'issuer_en' => 'Old Issuer',
        'description_ar' => 'وصف قديم',
        'description_en' => 'Old description',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('certificates-awards.update', $record), [
            'type' => CertificateAwardType::Certificate->value,
            'title_ar' => 'عنوان محدث',
            'title_en' => 'Updated Title',
            'issuer_ar' => 'جهة محدثة',
            'issuer_en' => 'Updated Issuer',
            'description_ar' => 'وصف محدث',
            'description_en' => 'Updated description',
            'issued_date' => '2025-01-01',
            'external_url' => 'https://updated.example.com',
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->title_ar)->toBe('عنوان محدث')
        ->and($record->title_en)->toBe('Updated Title')
        ->and($record->issuer_ar)->toBe('جهة محدثة')
        ->and($record->issuer_en)->toBe('Updated Issuer')
        ->and($record->description_ar)->toBe('وصف محدث')
        ->and($record->description_en)->toBe('Updated description');
});

test('admin can update image', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_en' => 'Image Record',
            'ordering' => 0,
        ]));

    $record = CertificateAward::where('title_en', 'Image Record')->first();
    $oldPath = $record->attachment->path;

    $this->actingAs($this->admin)
        ->post(route('certificates-awards.update', $record), [
            'type' => CertificateAwardType::Certificate->value,
            'title_ar' => $record->title_ar,
            'title_en' => 'Image Record',
            'issuer_ar' => $record->issuer_ar,
            'issuer_en' => $record->issuer_en,
            'description_ar' => $record->description_ar,
            'description_en' => $record->description_en,
            'issued_date' => $record->issued_date?->format('Y-m-d'),
            'external_url' => $record->external_url,
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

test('admin can delete a certificate award record', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_en' => 'Delete Me',
            'ordering' => 0,
        ]));

    $record = CertificateAward::where('title_en', 'Delete Me')->first();
    $path = $record->attachment->path;

    $this->actingAs($this->admin)
        ->delete(route('certificates-awards.destroy', $record))
        ->assertRedirect();

    expect(CertificateAward::find($record->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('certificates awards search finds arabic title', function () {
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'شهادة فريدة',
        'title_en' => 'Unique Certificate',
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'شهادة أخرى',
        'title_en' => 'Other Certificate',
    ]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['search' => 'فريدة']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.title_ar', 'شهادة فريدة'));
});

test('certificates awards search finds english title', function () {
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'شهادة فريدة',
        'title_en' => 'Unique Certificate',
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'شهادة أخرى',
        'title_en' => 'Other Certificate',
    ]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.title_en', 'Unique Certificate'));
});

test('certificates awards search finds arabic issuer', function () {
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Cert A',
        'issuer_ar' => 'جهة فريدة',
        'issuer_en' => 'Unique Issuer',
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Cert B',
        'issuer_ar' => 'جهة أخرى',
        'issuer_en' => 'Other Issuer',
    ]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['search' => 'فريدة']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.issuer_ar', 'جهة فريدة'));
});

test('certificates awards search finds english issuer', function () {
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Cert A',
        'issuer_en' => 'Unique Issuer',
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Cert B',
        'issuer_en' => 'Other Issuer',
    ]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['search' => 'Unique Issuer']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.issuer_en', 'Unique Issuer'));
});

test('certificates awards search finds description', function () {
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Cert A',
        'description_en' => 'Unique description text',
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Cert B',
        'description_en' => 'Other description',
    ]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['search' => 'Unique description']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.description_en', 'Unique description text'));
});

test('certificates awards index filters certificates only', function () {
    CertificateAward::factory()->certificate()->create(['title_en' => 'Visible Certificate']);
    CertificateAward::factory()->award()->create(['title_en' => 'Hidden Award']);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['type' => 'certificate']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.title_en', 'Visible Certificate')
            ->where('filters.type', 'certificate'));
});

test('certificates awards index filters awards only', function () {
    CertificateAward::factory()->certificate()->create(['title_en' => 'Hidden Certificate']);
    CertificateAward::factory()->award()->create(['title_en' => 'Visible Award']);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['type' => 'award']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 1)
            ->where('certificateAwards.data.0.title_en', 'Visible Award')
            ->where('filters.type', 'award'));
});

test('certificates awards index ignores invalid type filter', function () {
    CertificateAward::factory()->certificate()->create(['title_en' => 'Certificate A']);
    CertificateAward::factory()->award()->create(['title_en' => 'Award A']);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.index', ['type' => 'invalid']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificateAwards.data', 2)
            ->where('filters.type', 'all'));
});

test('next ordering requires valid type', function () {
    $this->actingAs($this->admin)
        ->get(route('certificates-awards.next-ordering'))
        ->assertSessionHasErrors('type');
});

test('admin can fetch next certificate ordering', function () {
    CertificateAward::factory()->certificate()->create(['ordering' => 3]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.next-ordering', ['type' => CertificateAwardType::Certificate->value]))
        ->assertOk()
        ->assertJson(['ordering' => 4]);
});

test('admin can fetch next award ordering', function () {
    CertificateAward::factory()->award()->create(['ordering' => 2]);

    $this->actingAs($this->admin)
        ->get(route('certificates-awards.next-ordering', ['type' => CertificateAwardType::Award->value]))
        ->assertOk()
        ->assertJson(['ordering' => 3]);
});

test('certificate ordering shifts do not affect awards', function () {
    $certificate = CertificateAward::factory()->certificate()->create(['ordering' => 1]);
    $award = CertificateAward::factory()->award()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'type' => CertificateAwardType::Certificate->value,
            'title_en' => 'Inserted Certificate',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    expect($certificate->fresh()->ordering)->toBe(2)
        ->and($award->fresh()->ordering)->toBe(1)
        ->and(CertificateAward::where('title_en', 'Inserted Certificate')->first()?->ordering)->toBe(1);
});

test('award ordering shifts do not affect certificates', function () {
    $certificate = CertificateAward::factory()->certificate()->create(['ordering' => 1]);
    $award = CertificateAward::factory()->award()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'type' => CertificateAwardType::Award->value,
            'title_en' => 'Inserted Award',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    expect($award->fresh()->ordering)->toBe(2)
        ->and($certificate->fresh()->ordering)->toBe(1)
        ->and(CertificateAward::where('title_en', 'Inserted Award')->first()?->ordering)->toBe(1);
});

test('deleting a certificate shifts only certificate ordering', function () {
    $firstCertificate = CertificateAward::factory()->certificate()->create(['ordering' => 1]);
    $secondCertificate = CertificateAward::factory()->certificate()->create(['ordering' => 2]);
    $award = CertificateAward::factory()->award()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->delete(route('certificates-awards.destroy', $firstCertificate))
        ->assertRedirect();

    expect($secondCertificate->fresh()->ordering)->toBe(1)
        ->and($award->fresh()->ordering)->toBe(1);
});

test('creating a certificate records activity with bilingual fields', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_ar' => 'شهادة مسجلة',
            'title_en' => 'Logged Certificate',
            'type' => CertificateAwardType::Certificate->value,
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new CertificateAward)->getMorphClass())
        ->and($log->new_values)->toHaveKey('title_ar', 'شهادة مسجلة')
        ->and($log->new_values)->toHaveKey('title_en', 'Logged Certificate')
        ->and($log->new_values)->toHaveKey('type', CertificateAwardType::Certificate->value)
        ->and($log->new_values)->not->toHaveKey('image');
});

test('creating an award records activity with type', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_en' => 'Logged Award',
            'type' => CertificateAwardType::Award->value,
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log->new_values)->toHaveKey('type', CertificateAwardType::Award->value);
});

test('deleting a certificate award records activity', function () {
    $this->actingAs($this->admin)
        ->post(route('certificates-awards.store'), validCertificateAwardPayload([
            'title_ar' => 'سجل محذوف',
            'title_en' => 'Deleted Record',
            'ordering' => 0,
        ]));

    $record = CertificateAward::where('title_en', 'Deleted Record')->first();

    $this->actingAs($this->admin)
        ->delete(route('certificates-awards.destroy', $record))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Deleted)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('title_ar', 'سجل محذوف');
});

test('homepage shows only active certificates in ordering', function () {
    Storage::fake('public');

    $first = CertificateAward::factory()->certificate()->create([
        'title_ar' => 'الشهادة الأولى',
        'title_en' => 'First Certificate',
        'ordering' => 1,
        'is_active' => true,
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'الشهادة الثانية',
        'title_en' => 'Second Certificate',
        'ordering' => 2,
        'is_active' => true,
    ]);
    CertificateAward::factory()->certificate()->inactive()->create([
        'title_en' => 'Hidden Certificate',
        'ordering' => 0,
    ]);

    $firstPath = UploadedFile::fake()->image('first.jpg')->store('certificates-awards', 'public');
    $first->attachment()->create(['name' => 'first.jpg', 'path' => $firstPath]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificates', 2)
            ->where('certificates.0.title_en', 'First Certificate')
            ->where('certificates.1.title_en', 'Second Certificate')
            ->where('certificates.0.image', asset('storage/'.$firstPath))
            ->missing('certificates.0.id')
            ->missing('certificates.0.ordering')
            ->missing('certificates.0.is_active')
            ->missing('certificates.0.type'));
});

test('homepage shows only active awards in ordering', function () {
    CertificateAward::factory()->award()->create([
        'title_en' => 'First Award',
        'ordering' => 1,
        'is_active' => true,
    ]);
    CertificateAward::factory()->award()->inactive()->create([
        'title_en' => 'Hidden Award',
        'ordering' => 0,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('awards', 1)
            ->where('awards.0.title_en', 'First Award')
            ->missing('awards.0.id')
            ->missing('awards.0.type'));
});

test('homepage works with zero certificates and awards', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificates', [])
            ->where('awards', []));
});

test('homepage public certificate award payload excludes admin-only fields', function () {
    CertificateAward::factory()->certificate()->create([
        'title_ar' => 'شهادة عامة',
        'title_en' => 'Public Certificate',
        'issuer_ar' => 'جهة عامة',
        'issuer_en' => 'Public Issuer',
        'description_ar' => 'وصف عام',
        'description_en' => 'Public description',
        'issued_date' => '2024-01-01',
        'external_url' => 'https://cert.test',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificates.0.title_ar', 'شهادة عامة')
            ->where('certificates.0.title_en', 'Public Certificate')
            ->where('certificates.0.issuer_ar', 'جهة عامة')
            ->where('certificates.0.issuer_en', 'Public Issuer')
            ->where('certificates.0.description_ar', 'وصف عام')
            ->where('certificates.0.description_en', 'Public description')
            ->where('certificates.0.issued_date', '2024-01-01')
            ->where('certificates.0.external_url', 'https://cert.test')
            ->missing('certificates.0.is_active')
            ->missing('certificates.0.ordering')
            ->missing('certificates.0.id')
            ->missing('certificates.0.created_at')
            ->missing('certificates.0.updated_at')
            ->missing('certificates.0.type'));
});

test('certificate and award ordering are independent on homepage', function () {
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'Second Certificate',
        'ordering' => 2,
        'is_active' => true,
    ]);
    CertificateAward::factory()->certificate()->create([
        'title_en' => 'First Certificate',
        'ordering' => 1,
        'is_active' => true,
    ]);
    CertificateAward::factory()->award()->create([
        'title_en' => 'Second Award',
        'ordering' => 2,
        'is_active' => true,
    ]);
    CertificateAward::factory()->award()->create([
        'title_en' => 'First Award',
        'ordering' => 1,
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificates.0.title_en', 'First Certificate')
            ->where('certificates.1.title_en', 'Second Certificate')
            ->where('awards.0.title_en', 'First Award')
            ->where('awards.1.title_en', 'Second Award'));
});
