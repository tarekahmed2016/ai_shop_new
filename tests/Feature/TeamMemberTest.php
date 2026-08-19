<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\TeamMember;
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

function validTeamMemberPayload(array $overrides = []): array
{
    return array_merge([
        'name_ar' => 'أحمد محمد',
        'name_en' => 'Ahmed Mohamed',
        'position_ar' => 'مدير تقني',
        'position_en' => 'Technical Director',
        'bio_ar' => 'خبرة واسعة في إدارة المشاريع التقنية.',
        'bio_en' => 'Extensive experience in technical project management.',
        'email' => 'ahmed@example.com',
        'phone' => '0123456789',
        'linkedin_url' => 'https://linkedin.com/in/ahmed',
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('member.jpg'),
    ], $overrides);
}

test('guest cannot open team members index', function () {
    $this->get(route('team-members.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot open team members index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('team-members.index'))
        ->assertRedirect(route('login'));
});

test('non admin cannot create a team member', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('team-members.store'), validTeamMemberPayload())
        ->assertRedirect(route('login'));

    expect(TeamMember::count())->toBe(0);
});

test('non admin cannot update a team member', function () {
    $teamMember = TeamMember::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('team-members.update', $teamMember), validTeamMemberPayload())
        ->assertRedirect(route('login'));
});

test('non admin cannot delete a team member', function () {
    $teamMember = TeamMember::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('team-members.destroy', $teamMember))
        ->assertRedirect(route('login'));

    expect(TeamMember::find($teamMember->id))->not->toBeNull();
});

test('non admin cannot fetch next ordering', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('team-members.next-ordering'))
        ->assertRedirect(route('login'));
});

test('admin can view team members index', function () {
    TeamMember::factory()->create([
        'name_ar' => 'سارة علي',
        'name_en' => 'Sara Ali',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TeamMembers/TeamMembersPage', false)
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.name_ar', 'سارة علي')
            ->where('teamMembers.data.0.name_en', 'Sara Ali'));
});

test('admin can create a bilingual team member', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_ar' => 'عضو جديد',
            'name_en' => 'New Member',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    $teamMember = TeamMember::where('name_en', 'New Member')->first();

    expect($teamMember)->not->toBeNull()
        ->and($teamMember->name_ar)->toBe('عضو جديد')
        ->and($teamMember->name_en)->toBe('New Member')
        ->and($teamMember->position_ar)->toBe('مدير تقني')
        ->and($teamMember->position_en)->toBe('Technical Director')
        ->and($teamMember->bio_ar)->toBe('خبرة واسعة في إدارة المشاريع التقنية.')
        ->and($teamMember->bio_en)->toBe('Extensive experience in technical project management.')
        ->and($teamMember->email)->toBe('ahmed@example.com')
        ->and($teamMember->phone)->toBe('0123456789')
        ->and($teamMember->linkedin_url)->toBe('https://linkedin.com/in/ahmed')
        ->and($teamMember->ordering)->toBe(1)
        ->and($teamMember->is_active)->toBeTrue()
        ->and($teamMember->attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($teamMember->attachment->path);
});

test('creating a team member requires name_ar', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload(['name_ar' => '']))
        ->assertSessionHasErrors('name_ar');

    expect(TeamMember::count())->toBe(0);
});

test('creating a team member requires name_en', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload(['name_en' => '']))
        ->assertSessionHasErrors('name_en');

    expect(TeamMember::count())->toBe(0);
});

test('creating a team member rejects name max lengths', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_en' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('name_en');
});

test('creating a team member validates bio max length', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'bio_en' => str_repeat('a', 15001),
        ]))
        ->assertSessionHasErrors('bio_en');
});

test('creating a team member rejects invalid email', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'email' => 'not-an-email',
        ]))
        ->assertSessionHasErrors('email');
});

test('creating a team member rejects invalid linkedin url', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'linkedin_url' => 'not-a-url',
        ]))
        ->assertSessionHasErrors('linkedin_url');
});

test('creating a team member rejects invalid ordering', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'ordering' => -1,
        ]))
        ->assertSessionHasErrors('ordering');
});

test('creating a team member requires status', function () {
    $payload = validTeamMemberPayload();
    unset($payload['is_active']);

    $this->actingAs($this->admin)
        ->post(route('team-members.store'), $payload)
        ->assertSessionHasErrors('is_active');

    expect(TeamMember::count())->toBe(0);
});

test('creating a team member requires an image', function () {
    $payload = validTeamMemberPayload();
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('team-members.store'), $payload)
        ->assertSessionHasErrors('image');

    expect(TeamMember::count())->toBe(0);
});

test('creating a team member rejects svg image', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'image' => UploadedFile::fake()->create('member.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('image');

    expect(TeamMember::count())->toBe(0);
});

test('creating a team member rejects oversized image', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'image' => UploadedFile::fake()->image('member.jpg')->size(5000),
        ]))
        ->assertSessionHasErrors('image');
});

test('creating a team member accepts png image', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_en' => 'PNG Member',
            'image' => UploadedFile::fake()->image('member.png'),
        ]))
        ->assertRedirect();

    expect(TeamMember::where('name_en', 'PNG Member')->exists())->toBeTrue();
});

test('admin can update arabic fields', function () {
    $teamMember = TeamMember::factory()->create([
        'name_ar' => 'عضو قديم',
        'name_en' => 'Old Member',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('team-members.update', $teamMember), [
            'name_ar' => 'عضو محدث',
            'name_en' => 'Old Member',
            'position_ar' => $teamMember->position_ar,
            'position_en' => $teamMember->position_en,
            'bio_ar' => $teamMember->bio_ar,
            'bio_en' => $teamMember->bio_en,
            'email' => $teamMember->email,
            'phone' => $teamMember->phone,
            'linkedin_url' => $teamMember->linkedin_url,
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($teamMember->fresh()->name_ar)->toBe('عضو محدث')
        ->and($teamMember->fresh()->name_en)->toBe('Old Member');
});

test('admin can update english fields', function () {
    $teamMember = TeamMember::factory()->create([
        'name_ar' => 'عضو قديم',
        'name_en' => 'Old Member',
        'ordering' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('team-members.update', $teamMember), [
            'name_ar' => 'عضو قديم',
            'name_en' => 'Updated Member',
            'position_ar' => $teamMember->position_ar,
            'position_en' => 'Updated Position',
            'bio_ar' => $teamMember->bio_ar,
            'bio_en' => 'Updated bio',
            'email' => 'updated@example.com',
            'phone' => $teamMember->phone,
            'linkedin_url' => $teamMember->linkedin_url,
            'ordering' => 0,
            'is_active' => false,
        ])
        ->assertRedirect();

    $teamMember->refresh();

    expect($teamMember->name_en)->toBe('Updated Member')
        ->and($teamMember->position_en)->toBe('Updated Position')
        ->and($teamMember->bio_en)->toBe('Updated bio')
        ->and($teamMember->email)->toBe('updated@example.com')
        ->and($teamMember->is_active)->toBeFalse();
});

test('admin can update a team member image', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_en' => 'Image Member',
            'ordering' => 0,
        ]));

    $teamMember = TeamMember::where('name_en', 'Image Member')->first();
    $oldPath = $teamMember->attachment->path;

    $this->actingAs($this->admin)
        ->post(route('team-members.update', $teamMember), [
            'name_ar' => $teamMember->name_ar,
            'name_en' => 'Image Member',
            'position_ar' => $teamMember->position_ar,
            'position_en' => $teamMember->position_en,
            'bio_ar' => $teamMember->bio_ar,
            'bio_en' => 'Updated with image',
            'email' => $teamMember->email,
            'phone' => $teamMember->phone,
            'linkedin_url' => $teamMember->linkedin_url,
            'ordering' => 0,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('updated.jpg'),
            '_method' => 'put',
        ])
        ->assertRedirect();

    $teamMember->refresh();

    expect($teamMember->attachment)->not->toBeNull()
        ->and($teamMember->attachment->path)->not->toBe($oldPath);

    Storage::disk('public')->assertExists($teamMember->attachment->path);
    Storage::disk('public')->assertMissing($oldPath);
});

test('admin can delete a team member', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_en' => 'Delete Me',
            'ordering' => 0,
        ]));

    $teamMember = TeamMember::where('name_en', 'Delete Me')->first();
    $path = $teamMember->attachment->path;

    $this->actingAs($this->admin)
        ->delete(route('team-members.destroy', $teamMember))
        ->assertRedirect();

    expect(TeamMember::find($teamMember->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('team member admin search finds arabic name', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو فريد',
        'name_en' => 'Unique Member',
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'عضو آخر',
        'name_en' => 'Other Member',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index', ['search' => 'فريد']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.name_ar', 'عضو فريد'));
});

test('team member admin search finds english name', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو فريد',
        'name_en' => 'Unique Member',
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'عضو آخر',
        'name_en' => 'Other Member',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.name_en', 'Unique Member'));
});

test('team member admin search finds arabic position', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو أ',
        'name_en' => 'Member A',
        'position_ar' => 'منصب فريد',
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'عضو ب',
        'name_en' => 'Member B',
        'position_ar' => 'منصب آخر',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index', ['search' => 'فريد']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.name_en', 'Member A'));
});

test('team member admin search finds english position', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو أ',
        'name_en' => 'Member A',
        'position_en' => 'Unique Position',
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'عضو ب',
        'name_en' => 'Member B',
        'position_en' => 'Other Position',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index', ['search' => 'Unique Position']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.name_en', 'Member A'));
});

test('team member admin search finds email', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو أ',
        'name_en' => 'Member A',
        'email' => 'unique@example.com',
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'عضو ب',
        'name_en' => 'Member B',
        'email' => 'other@example.com',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index', ['search' => 'unique@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.email', 'unique@example.com'));
});

test('team member admin search finds phone', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو أ',
        'name_en' => 'Member A',
        'phone' => '999888777',
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'عضو ب',
        'name_en' => 'Member B',
        'phone' => '111222333',
    ]);

    $this->actingAs($this->admin)
        ->get(route('team-members.index', ['search' => '999888777']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers.data', 1)
            ->where('teamMembers.data.0.phone', '999888777'));
});

test('creating a team member records activity with bilingual fields', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_ar' => 'عضو مسجل',
            'name_en' => 'Logged Member',
            'ordering' => 0,
        ]))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new TeamMember)->getMorphClass())
        ->and($log->new_values)->toHaveKey('name_ar', 'عضو مسجل')
        ->and($log->new_values)->toHaveKey('name_en', 'Logged Member')
        ->and($log->new_values)->not->toHaveKey('image');
});

test('updating a team member records activity for changed bilingual fields', function () {
    $teamMember = TeamMember::factory()->create([
        'name_ar' => 'عضو أصلي',
        'name_en' => 'Original Member',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('team-members.update', $teamMember), [
            'name_ar' => 'عضو معاد تسميته',
            'name_en' => 'Renamed Member',
            'position_ar' => $teamMember->position_ar,
            'position_en' => $teamMember->position_en,
            'bio_ar' => $teamMember->bio_ar,
            'bio_en' => $teamMember->bio_en,
            'email' => $teamMember->email,
            'phone' => $teamMember->phone,
            'linkedin_url' => $teamMember->linkedin_url,
            'ordering' => $teamMember->ordering,
            'is_active' => false,
        ])
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Updated)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKey('name_en', 'Original Member')
        ->and($log->new_values)->toHaveKey('name_en', 'Renamed Member')
        ->and($log->new_values)->toHaveKey('is_active', false);
});

test('deleting a team member records activity', function () {
    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_ar' => 'عضو محذوف',
            'name_en' => 'Deleted Member',
            'ordering' => 0,
        ]));

    $teamMember = TeamMember::where('name_en', 'Deleted Member')->first();

    $this->actingAs($this->admin)
        ->delete(route('team-members.destroy', $teamMember))
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Deleted)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe((new TeamMember)->getMorphClass())
        ->and($log->old_values)->toHaveKey('name_ar', 'عضو محذوف');
});

test('admin can fetch next ordering', function () {
    TeamMember::factory()->create(['ordering' => 3]);

    $this->actingAs($this->admin)
        ->get(route('team-members.next-ordering'))
        ->assertOk()
        ->assertJson(['ordering' => 4]);
});

test('creating a team member shifts existing ordering values', function () {
    $existing = TeamMember::factory()->create(['ordering' => 1]);

    $this->actingAs($this->admin)
        ->post(route('team-members.store'), validTeamMemberPayload([
            'name_en' => 'Inserted Member',
            'ordering' => 1,
        ]))
        ->assertRedirect();

    expect($existing->fresh()->ordering)->toBe(2)
        ->and(TeamMember::where('name_en', 'Inserted Member')->first()?->ordering)->toBe(1);
});

test('deleting a team member shifts down remaining ordering values', function () {
    $first = TeamMember::factory()->create(['ordering' => 1]);
    $second = TeamMember::factory()->create(['ordering' => 2]);

    $this->actingAs($this->admin)
        ->delete(route('team-members.destroy', $first))
        ->assertRedirect();

    expect($second->fresh()->ordering)->toBe(1);
});

test('homepage shows only active team members in ordering', function () {
    Storage::fake('public');

    $first = TeamMember::factory()->create([
        'name_ar' => 'العضو الأول',
        'name_en' => 'First Member',
        'ordering' => 1,
        'is_active' => true,
    ]);
    TeamMember::factory()->create([
        'name_ar' => 'العضو الثاني',
        'name_en' => 'Second Member',
        'ordering' => 2,
        'is_active' => true,
    ]);
    TeamMember::factory()->inactive()->create([
        'name_ar' => 'عضو مخفي',
        'name_en' => 'Hidden Member',
        'ordering' => 0,
    ]);

    $firstPath = UploadedFile::fake()->image('first.jpg')->store('team-members', 'public');
    $first->attachment()->create(['name' => 'first.jpg', 'path' => $firstPath]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teamMembers', 2)
            ->where('teamMembers.0.name_en', 'First Member')
            ->where('teamMembers.1.name_en', 'Second Member')
            ->where('teamMembers.0.name_ar', 'العضو الأول')
            ->where('teamMembers.0.image', asset('storage/'.$firstPath))
            ->missing('teamMembers.0.id')
            ->missing('teamMembers.0.ordering')
            ->missing('teamMembers.0.is_active'));
});

test('homepage works with zero team members', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('teamMembers', []));
});

test('homepage public team member payload excludes admin-only fields', function () {
    TeamMember::factory()->create([
        'name_ar' => 'عضو عام',
        'name_en' => 'Public Member',
        'position_ar' => 'منصب عربي',
        'position_en' => 'Public Position',
        'bio_ar' => 'نبذة عربية',
        'bio_en' => 'English bio',
        'email' => 'member@example.com',
        'phone' => '0123456789',
        'linkedin_url' => 'https://linkedin.com/in/member',
        'is_active' => true,
        'ordering' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('teamMembers.0.name_ar', 'عضو عام')
            ->where('teamMembers.0.name_en', 'Public Member')
            ->where('teamMembers.0.position_ar', 'منصب عربي')
            ->where('teamMembers.0.position_en', 'Public Position')
            ->where('teamMembers.0.bio_ar', 'نبذة عربية')
            ->where('teamMembers.0.bio_en', 'English bio')
            ->where('teamMembers.0.email', 'member@example.com')
            ->where('teamMembers.0.phone', '0123456789')
            ->where('teamMembers.0.linkedin_url', 'https://linkedin.com/in/member')
            ->missing('teamMembers.0.is_active')
            ->missing('teamMembers.0.ordering')
            ->missing('teamMembers.0.id')
            ->missing('teamMembers.0.created_at')
            ->missing('teamMembers.0.updated_at'));
});
