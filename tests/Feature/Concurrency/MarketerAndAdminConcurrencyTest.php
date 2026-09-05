<?php

use App\Enums\MarketerCommissions\CommissionType;
use App\Enums\MarketerCommissions\Status as CommissionStatus;
use App\Enums\Payments\Method;
use App\Enums\Payments\Status as PaymentStatus;
use App\Enums\Payments\Type as PaymentType;
use App\Enums\Users\Status as UserStatus;
use App\Models\Marketer;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\MarketerReferral;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\AdminGuardService;
use App\Services\MarketerCommissionService;
use App\Services\UserService;
use App\Support\AdminPermissionCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Support\Concurrency\ConcurrentProcesses;

beforeEach(function () {
    if (! ConcurrentProcesses::supported()) {
        $this->markTestSkipped('pcntl_fork is required for overlapping concurrency tests.');
    }

    SpatieRole::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
});

test('same qualifying payment cannot create duplicate commissions under overlap', function () {
    $marketer = Marketer::factory()->create();
    $payer = User::factory()->create();
    MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $payer->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);

    $payment = PaymentTransaction::factory()->create([
        'payer_user_id' => $payer->id,
        'type' => PaymentType::CustomerExtraRequests,
        'status' => PaymentStatus::Paid,
        'amount' => '10.000',
        'paid_at' => now(),
    ]);

    $paymentId = (int) $payment->id;

    ConcurrentProcesses::map(2, function () use ($paymentId) {
        $payment = PaymentTransaction::query()->findOrFail($paymentId);

        return app(MarketerCommissionService::class)->createForPaidPayment($payment)?->id;
    });

    expect(MarketerCommission::query()->where('payment_transaction_id', $paymentId)->count())->toBe(1);
});

test('concurrent payouts cannot overpay remaining commission', function () {
    $marketer = Marketer::factory()->create();
    $payer = User::factory()->create();
    MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $payer->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);
    $payment = PaymentTransaction::factory()->create([
        'payer_user_id' => $payer->id,
        'type' => PaymentType::CustomerExtraRequests,
        'status' => PaymentStatus::Paid,
        'amount' => '10.000',
        'paid_at' => now(),
    ]);
    MarketerCommission::query()->create([
        'marketer_id' => $marketer->id,
        'marketer_referral_id' => MarketerReferral::query()->where('referred_user_id', $payer->id)->value('id'),
        'payment_transaction_id' => $payment->id,
        'referred_user_id' => $payer->id,
        'payment_type' => PaymentType::CustomerExtraRequests,
        'payment_amount' => '10.000',
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => '10.000',
        'commission_amount' => '5.000',
        'status' => CommissionStatus::Approved,
        'earned_at' => now(),
    ]);

    $actor = User::factory()->create();
    $marketerId = (int) $marketer->id;
    $actorId = (int) $actor->id;

    $results = ConcurrentProcesses::map(2, function () use ($marketerId, $actorId) {
        try {
            app(MarketerCommissionService::class)->recordPayout(
                Marketer::query()->findOrFail($marketerId),
                '5.000',
                Method::Cash,
                User::query()->findOrFail($actorId),
                null,
                'overlap',
            );

            return true;
        } catch (ValidationException) {
            return false;
        }
    });

    $accepted = count(array_filter(ConcurrentProcesses::values($results)));
    $paid = (string) MarketerPayout::query()->where('marketer_id', $marketerId)->sum('amount');
    $outstanding = app(MarketerCommissionService::class)->financialSummary(Marketer::query()->findOrFail($marketerId))['outstanding'];

    if (! $this->usesInnoDbRowLocks() && ($accepted !== 1 || bccomp($outstanding, '0.000', 3) !== 0)) {
        $this->markTestSkipped('SQLite cannot prove Marketer::lockForUpdate() payout serialization. Re-run with CONCURRENCY_DB=mariadb.');
    }

    expect($accepted)->toBe(1)
        ->and(bcadd($paid, '0', 3))->toBe('5.000')
        ->and(bccomp((string) $outstanding, '0.000', 3))->toBe(0);
});

test('two concurrent last-admin demotions cannot remove every administrator', function () {
    if (! $this->usesInnoDbRowLocks()) {
        $this->markTestSkipped('SQLite cannot serialize overlapping Spatie role-pivot writes (database is locked). Re-run with CONCURRENCY_DB=mariadb.');
    }
    $first = adminWithPermissions(AdminPermissionCatalog::names());
    $second = User::factory()->create();
    $second->assignRole('admin');

    User::role('admin')->whereNotIn('id', [$first->id, $second->id])->each(function (User $extra): void {
        $extra->syncRoles(['member']);
    });

    expect(app(AdminGuardService::class)->adminUserCount())->toBe(2);

    $firstId = (int) $first->id;
    $secondId = (int) $second->id;

    $results = ConcurrentProcesses::map(2, function (int $index) use ($firstId, $secondId) {
        $actorId = $index === 0 ? $firstId : $secondId;
        $targetId = $index === 0 ? $secondId : $firstId;
        $actor = User::query()->findOrFail($actorId);
        $actor->load(['roles', 'permissions']);
        Auth::login($actor);
        app('request')->setUserResolver(fn () => User::query()->find($actorId));
        $target = User::query()->findOrFail($targetId);

        try {
            app(UserService::class)->update($target, [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'status' => UserStatus::Active,
                'role' => 'member',
            ]);

            return true;
        } catch (ValidationException) {
            return false;
        }
    });

    $remaining = app(AdminGuardService::class)->adminUserCount();
    $sqliteLocked = collect($results)->contains(
        fn (array $row) => str_contains((string) ($row['error'] ?? ''), 'database is locked'),
    );

    if (! $this->usesInnoDbRowLocks() && $sqliteLocked) {
        expect($remaining)->toBeGreaterThanOrEqual(1);

        return;
    }

    if (! $sqliteLocked) {
        ConcurrentProcesses::assertAllOk($results);
    }

    expect($remaining)->toBeGreaterThanOrEqual(1)
        ->and(count(array_filter(ConcurrentProcesses::values($results))))->toBeLessThanOrEqual(1);
});

test('two concurrent deletes cannot remove the last remaining administrator', function () {
    $actor = adminWithPermissions(AdminPermissionCatalog::names());
    $only = User::factory()->create();
    $only->assignRole('admin');
    $actor->removeRole('admin');

    User::role('admin')->where('id', '!=', $only->id)->each(function (User $extra): void {
        $extra->syncRoles(['member']);
    });

    expect(app(AdminGuardService::class)->adminUserCount())->toBe(1);

    $actorId = (int) $actor->id;
    $onlyId = (int) $only->id;

    $results = ConcurrentProcesses::map(2, function () use ($actorId, $onlyId) {
        Auth::loginUsingId($actorId);
        app('request')->setUserResolver(fn () => User::query()->find($actorId));

        try {
            app(UserService::class)->delete(User::query()->findOrFail($onlyId));

            return true;
        } catch (ValidationException) {
            return false;
        } catch (ModelNotFoundException) {
            return false;
        }
    });

    ConcurrentProcesses::assertAllOk($results);

    expect(count(array_filter(ConcurrentProcesses::values($results))))->toBe(0)
        ->and(User::role('admin')->count())->toBe(1)
        ->and(User::query()->whereKey($onlyId)->exists())->toBeTrue();
});
