<?php

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['name' => 'Platform Admin']);
    $this->admin->assignRole('admin');
});

test('user activity records a verified actor', function () {
    $subject = User::factory()->create(['name' => 'Logged User']);
    $this->actingAs($this->admin);

    $activityLog = app(ActivityLogService::class)->recordCreated(
        subject: $subject,
        allowedFields: ['name', 'email', 'phone', 'status'],
    );

    expect($activityLog->actor->is($this->admin))->toBeTrue()
        ->and($activityLog->subject->is($subject))->toBeTrue()
        ->and($activityLog->actor_name)->toBe('Platform Admin')
        ->and($activityLog->source)->toBe(ActivityLog::SourceUser);
});

test('user activity cannot silently become system activity', function () {
    $subject = User::factory()->create();

    expect(fn () => app(ActivityLogService::class)->recordCreated(
        subject: $subject,
        allowedFields: ['name'],
    ))->toThrow(LogicException::class);

    expect(ActivityLog::count())->toBe(0);
});

test('update activity records only genuinely changed safe fields', function () {
    $subject = User::factory()->create([
        'name' => 'Original Name',
        'phone' => '111',
    ]);
    $originalValues = $subject->only(['name', 'phone']);

    $subject->update([
        'name' => 'Updated Name',
        'phone' => '111',
    ]);

    $activityLog = app(ActivityLogService::class)->recordChanges(
        subject: $subject,
        originalValues: $originalValues,
        allowedFields: ['name', 'phone'],
        actor: $this->admin,
    );

    expect($activityLog)->not->toBeNull()
        ->and($activityLog->old_values)->toBe(['name' => 'Original Name'])
        ->and($activityLog->new_values)->toBe(['name' => 'Updated Name']);
});

test('sensitive keys are excluded from values and nested metadata', function () {
    $subject = User::factory()->create();

    $activityLog = app(ActivityLogService::class)->recordSystem(
        subject: $subject,
        event: Event::Updated,
        oldValues: [
            'name' => 'Old Name',
            'password' => 'secret',
        ],
        newValues: [
            'name' => 'New Name',
            'password' => 'secret',
            'access_token' => 'token',
        ],
        allowedFields: ['name', 'password', 'access_token'],
        metadata: [
            'reason' => 'account correction',
            'access_token' => 'token',
            'nested' => [
                'credential' => 'secret',
                'reference' => 'safe',
            ],
        ],
    );

    expect($activityLog->old_values)->toBe(['name' => 'Old Name'])
        ->and($activityLog->new_values)->toBe(['name' => 'New Name'])
        ->and($activityLog->metadata)->toBe([
            'reason' => 'account correction',
            'nested' => ['reference' => 'safe'],
        ]);
});

test('ordinary mass assignment cannot create activity logs directly', function () {
    $subject = User::factory()->create();

    expect(fn () => ActivityLog::create([
        'source' => ActivityLog::SourceSystem,
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'event' => Event::Created,
    ]))->toThrow(MassAssignmentException::class);

    expect(ActivityLog::count())->toBe(0);
});

test('recordChanges keeps metadata-only updates', function () {
    $subject = User::factory()->create(['name' => 'Unchanged']);
    $originalValues = $subject->only(['name']);

    $activityLog = app(ActivityLogService::class)->recordChanges(
        subject: $subject,
        originalValues: $originalValues,
        allowedFields: ['name'],
        metadata: ['action' => 'permissions.updated'],
        actor: $this->admin,
    );

    expect($activityLog)->not->toBeNull()
        ->and($activityLog->old_values)->toBeNull()
        ->and($activityLog->new_values)->toBeNull()
        ->and($activityLog->metadata)->toBe(['action' => 'permissions.updated']);
});

test('recordAction writes explicit before and after values without secrets', function () {
    $subject = User::factory()->create();

    $activityLog = app(ActivityLogService::class)->recordAction(
        subject: $subject,
        event: Event::Updated,
        oldValues: ['role' => 'staff', 'password' => 'secret'],
        newValues: ['role' => 'admin', 'password' => 'secret'],
        metadata: ['action' => 'user.role_changed', 'access_token' => 'tok'],
        actor: $this->admin,
    );

    expect($activityLog->actor_id)->toBe($this->admin->id)
        ->and($activityLog->old_values)->toBe(['role' => 'staff'])
        ->and($activityLog->new_values)->toBe(['role' => 'admin'])
        ->and($activityLog->metadata)->toBe(['action' => 'user.role_changed']);
});

test('creating a user through the service records activity', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Logged Staff',
            'email' => 'logged@example.com',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'admin',
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('event', Event::Created)->count())->toBe(1)
        ->and(ActivityLog::first()->new_values)->not->toHaveKey('password');
});
