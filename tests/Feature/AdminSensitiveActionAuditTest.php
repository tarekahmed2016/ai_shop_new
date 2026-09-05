<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\Marketers\Status as MarketerStatus;
use App\Enums\MerchantOfferCredits\TransactionSource as CreditSource;
use App\Enums\Payments\Method;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use App\Models\Marketer;
use App\Models\MarketerPayout;
use App\Models\Merchant;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingChange;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\CustomerExtraRequestService;
use App\Services\MarketerCommissionService;
use App\Services\MarketerService;
use App\Services\MerchantOfferCreditService;
use App\Support\AdminPermissionCatalog;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

function latestActionLog(string $action): ?ActivityLog
{
    return ActivityLog::query()
        ->where('metadata->action', $action)
        ->latest('id')
        ->first();
}

test('marketer status transition writes one activity log and invalid retry writes none', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $marketer = Marketer::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('marketers.approve', $marketer))
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'marketer.status_changed')->count())->toBe(1);

    $log = latestActionLog('marketer.status_changed');
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($marketer->id)
        ->and($log->old_values)->toBe(['status' => MarketerStatus::Pending->value])
        ->and($log->new_values)->toBe(['status' => MarketerStatus::Active->value]);

    $this->actingAs($admin)
        ->post(route('marketers.approve', $marketer->fresh()))
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'marketer.status_changed')->count())->toBe(1)
        ->and($marketer->fresh()->status)->toBe(MarketerStatus::Active);
});

test('failed marketer transition writes no status audit row', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $marketer = Marketer::factory()->create();

    $before = ActivityLog::query()->where('metadata->action', 'marketer.status_changed')->count();

    $this->actingAs($admin)
        ->post(route('marketers.reject', $marketer))
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'marketer.status_changed')->count())->toBe($before)
        ->and($marketer->fresh()->status)->toBe(MarketerStatus::Active);
});

test('marketer rate override writes one minimal audit row and unchanged rates write none', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $marketer = Marketer::factory()->create();

    $this->actingAs($admin)
        ->put(route('marketers.commission-rates.update', $marketer), [
            'customer_commission_rate' => 12.5,
            'merchant_commission_rate' => 8,
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'marketer.rates_changed')->count())->toBe(1);

    $log = latestActionLog('marketer.rates_changed');
    expect($log->actor_id)->toBe($admin->id)
        ->and($log->old_values)->toHaveKeys(['customer_commission_rate', 'merchant_commission_rate'])
        ->and($log->new_values['customer_commission_rate'])->toBe('12.500')
        ->and($log->new_values['merchant_commission_rate'])->toBe('8.000');

    $this->actingAs($admin)
        ->put(route('marketers.commission-rates.update', $marketer->fresh()), [
            'customer_commission_rate' => 12.5,
            'merchant_commission_rate' => 8,
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'marketer.rates_changed')->count())->toBe(1);
});

test('admin-created marketer writes one created audit row', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $user = User::factory()->create(['email' => 'attach-marketer@example.test']);

    $this->actingAs($admin)
        ->post(route('marketers.store'), [
            'mode' => 'attach',
            'status' => MarketerStatus::Pending->value,
            'user_email' => 'attach-marketer@example.test',
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'marketer.admin_created')->count())->toBe(1);

    $log = latestActionLog('marketer.admin_created');
    expect($log->actor_id)->toBe($admin->id)
        ->and($log->new_values)->toBe(['status' => MarketerStatus::Pending->value])
        ->and($user->fresh()->marketer)->not->toBeNull();
});

test('granting and removing the admin role writes one role audit each', function () {
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $admin = adminWithPermissions([
        'users.update',
        AdminPermissionCatalog::MANAGE_ADMIN_ROLE,
    ]);
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($admin)
        ->put(route('users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'status' => 1,
            'role' => 'admin',
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'user.role_changed')->count())->toBe(1);

    $granted = latestActionLog('user.role_changed');
    expect($granted->actor_id)->toBe($admin->id)
        ->and($granted->old_values)->toBe(['role' => 'staff'])
        ->and($granted->new_values)->toBe(['role' => 'admin'])
        ->and($granted->new_values)->not->toHaveKey('password');

    $this->actingAs($admin)
        ->put(route('users.update', $staff->fresh()), [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'user.role_changed')->count())->toBe(2)
        ->and(latestActionLog('user.role_changed')->new_values)->toBe(['role' => 'staff']);
});

test('ordinary user field edits and blocked admin-role changes write no role audit', function () {
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $editor = adminWithPermissions(['users.update']);
    $staff = User::factory()->create(['name' => 'Before']);
    $staff->assignRole('staff');

    $this->actingAs($editor)
        ->put(route('users.update', $staff), [
            'name' => 'After',
            'email' => $staff->email,
            'phone' => $staff->phone,
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect();

    expect(ActivityLog::query()->where('metadata->action', 'user.role_changed')->count())->toBe(0)
        ->and($staff->fresh()->name)->toBe('After');

    $this->actingAs($editor)
        ->put(route('users.update', $staff->fresh()), [
            'name' => 'After',
            'email' => $staff->email,
            'phone' => $staff->phone,
            'status' => 1,
            'role' => 'admin',
        ])
        ->assertForbidden();

    expect(ActivityLog::query()->where('metadata->action', 'user.role_changed')->count())->toBe(0)
        ->and($staff->fresh()->hasRole('staff'))->toBeTrue();
});

test('last-admin demote writes no role audit', function () {
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertSessionHasErrors('role');

    expect(ActivityLog::query()->where('metadata->action', 'user.role_changed')->count())->toBe(0)
        ->and($admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('creating a user records role in metadata and never stores the password', function () {
    SpatieRole::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Audited Staff',
            'email' => 'audited-staff@example.test',
            'phone' => '0123456789',
            'password' => 'password',
            'status' => 1,
            'role' => 'staff',
        ])
        ->assertRedirect();

    $log = ActivityLog::query()->where('event', Event::Created)->where('subject_label', 'Audited Staff')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->metadata['role'])->toBe('staff')
        ->and($log->new_values)->not->toHaveKey('password')
        ->and($log->metadata)->not->toHaveKey('password');
});

test('permission-only role updates are persisted on activity logs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $permission = Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
    $role = SpatieRole::create(['name' => 'auditor', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'name' => 'auditor',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect();

    $log = ActivityLog::query()
        ->where('event', Event::Updated)
        ->where('subject_id', $role->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['permissions']['new'])->toContain($permission->id)
        ->and($log->metadata['permissions']['old'])->toBe([]);
});

test('manual merchant credits stay on the ledger and do not add activity-log duplicates', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $beforeLogs = ActivityLog::query()->count();

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 7,
            'source' => CreditSource::Cash->value,
            'notes' => 'manual add',
        ])
        ->assertRedirect();

    expect(MerchantOfferCreditTransaction::query()->count())->toBe(1)
        ->and(ActivityLog::query()->count())->toBe($beforeLogs);

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $merchant), [
            'amount' => 20,
            'source' => CreditSource::ManualAdjustment->value,
            'notes' => 'too much',
        ])
        ->assertSessionHasErrors('amount');

    expect(MerchantOfferCreditTransaction::query()->count())->toBe(1)
        ->and(ActivityLog::query()->count())->toBe($beforeLogs);
});

test('manual extra-request credits stay on the ledger and failed deducts write none', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $customer = Customer::factory()->create();
    $beforeLogs = ActivityLog::query()->count();

    $this->actingAs($admin);
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        3,
        ExtraSource::Cash,
        null,
        'manual',
        $admin,
    );

    expect(CustomerExtraRequestTransaction::query()->count())->toBe(1)
        ->and(ActivityLog::query()->count())->toBe($beforeLogs);

    expect(fn () => app(CustomerExtraRequestService::class)->deductCredits(
        $customer,
        9,
        ExtraSource::ManualAdjustment,
        'too much',
        null,
        $admin,
    ))->toThrow(ValidationException::class);

    expect(CustomerExtraRequestTransaction::query()->count())->toBe(1)
        ->and(ActivityLog::query()->count())->toBe($beforeLogs);
});

test('credit enforcement changes write one platform setting change and repeats write none', function () {
    $admin = creditAdmin();

    $this->actingAs($admin)
        ->put(route('merchants.credits.enforcement'), ['enabled' => true])
        ->assertRedirect();

    expect(PlatformSettingChange::query()->where('key', PlatformSetting::KEY_OFFER_CREDIT_ENFORCEMENT)->count())->toBe(1);

    $change = PlatformSettingChange::query()->where('key', PlatformSetting::KEY_OFFER_CREDIT_ENFORCEMENT)->first();
    expect($change->old_value)->toBe('0')
        ->and($change->new_value)->toBe('1')
        ->and($change->changed_by_user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->put(route('merchants.credits.enforcement'), ['enabled' => true])
        ->assertRedirect();

    expect(PlatformSettingChange::query()->where('key', PlatformSetting::KEY_OFFER_CREDIT_ENFORCEMENT)->count())->toBe(1);
    expect(app(MerchantOfferCreditService::class)->isEnforcementEnabled())->toBeTrue();
});

test('marketer payout remains ledger-only', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $marketer = Marketer::factory()->create();
    $beforeLogs = ActivityLog::query()->count();

    expect(fn () => app(MarketerCommissionService::class)->recordPayout(
        $marketer,
        '1.000',
        Method::Cash,
        $admin,
        null,
        null,
    ))->toThrow(ValidationException::class);

    expect(MarketerPayout::query()->count())->toBe(0)
        ->and(ActivityLog::query()->count())->toBe($beforeLogs);
});

test('rolled-back marketer status change writes no audit row', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    $marketer = Marketer::factory()->pending()->create();

    $this->mock(ActivityLogService::class, function ($mock) {
        $mock->shouldReceive('recordAction')->once()->andThrow(new RuntimeException('audit failed'));
    });

    expect(fn () => app(MarketerService::class)->approve($marketer->fresh()))
        ->toThrow(RuntimeException::class);

    expect($marketer->fresh()->status)->toBe(MarketerStatus::Pending)
        ->and(ActivityLog::query()->where('metadata->action', 'marketer.status_changed')->count())->toBe(0);
});
