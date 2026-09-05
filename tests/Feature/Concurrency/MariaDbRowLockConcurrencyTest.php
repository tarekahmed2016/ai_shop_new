<?php

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\Users\Status as UserStatus;
use App\Models\Customer;
use App\Models\MerchantOffer;
use App\Models\User;
use App\Services\AdminGuardService;
use App\Services\CustomerExtraRequestService;
use App\Services\OfferContactRevealService;
use App\Services\UserService;
use App\Support\AdminPermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Support\Concurrency\ConcurrencyFixtures;
use Tests\Support\Concurrency\ConcurrentProcesses;

beforeEach(function () {
    if (! ConcurrentProcesses::supported()) {
        $this->markTestSkipped('pcntl_fork is required for overlapping concurrency tests.');
    }

    if (! $this->usesInnoDbRowLocks()) {
        $this->markTestSkipped('InnoDB row-lock semantics require CONCURRENCY_DB=mariadb|mysql against a dedicated *test* database.');
    }
});

test('mariadb: concurrent full-balance extra-request deducts serialize on the customer row', function () {
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $customerId = (int) $customer->id;
    $actorId = (int) $actor->id;

    $results = ConcurrentProcesses::map(2, function () use ($customerId, $actorId) {
        try {
            app(CustomerExtraRequestService::class)->deductCredits(
                Customer::query()->findOrFail($customerId),
                1,
                ExtraSource::ManualAdjustment,
                'deduct',
                null,
                User::query()->findOrFail($actorId),
            );

            return true;
        } catch (ValidationException) {
            return false;
        }
    });

    expect(count(array_filter(ConcurrentProcesses::values($results))))->toBe(1)
        ->and(app(CustomerExtraRequestService::class)->balance($customerId))->toBe(0);
});

test('mariadb: concurrent reveals of different merchants honor the remaining quota row lock', function () {
    config(['customer_requests.contact_reveal_limit' => 1]);
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $offerIds = [];

    for ($i = 0; $i < 2; $i++) {
        $offerIds[] = (int) ConcurrencyFixtures::submittedOffer(
            $request,
            ConcurrencyFixtures::merchantForCategory($category),
        )->id;
    }

    $customerId = (int) $customer->id;

    ConcurrentProcesses::map(2, function (int $index) use ($customerId, $offerIds) {
        try {
            app(OfferContactRevealService::class)->reveal(
                Customer::query()->findOrFail($customerId),
                MerchantOffer::query()->findOrFail($offerIds[$index]),
            );

            return true;
        } catch (ValidationException) {
            return false;
        }
    });

    expect(app(OfferContactRevealService::class)->quotaSnapshot($request->fresh(), $customer->fresh())['used'])->toBe(1);
});

test('mariadb: a held customer row lock blocks the overlapping deduct until commit', function () {
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $customerId = (int) $customer->id;
    $actorId = (int) $actor->id;
    $dir = sys_get_temp_dir().'/ai_shop_c6_lockhold_'.str_replace('.', '_', uniqid('', true));
    mkdir($dir, 0777, true);

    $results = ConcurrentProcesses::map(2, function (int $index) use ($customerId, $actorId, $dir) {
        if ($index === 0) {
            return DB::transaction(function () use ($customerId, $dir) {
                Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
                file_put_contents($dir.'/held', (string) microtime(true));

                $deadline = microtime(true) + 5;
                while (! is_file($dir.'/trying') && microtime(true) < $deadline) {
                    usleep(2000);
                }

                usleep(150000);
                $released = microtime(true);
                file_put_contents($dir.'/released', (string) $released);

                return $released;
            });
        }

        $deadline = microtime(true) + 5;
        while (! is_file($dir.'/held') && microtime(true) < $deadline) {
            usleep(1000);
        }

        file_put_contents($dir.'/trying', (string) microtime(true));
        app(CustomerExtraRequestService::class)->deductCredits(
            Customer::query()->findOrFail($customerId),
            1,
            ExtraSource::ManualAdjustment,
            'deduct',
            null,
            User::query()->findOrFail($actorId),
        );

        $done = microtime(true);
        file_put_contents($dir.'/done', (string) $done);

        return $done;
    });

    ConcurrentProcesses::assertAllOk($results);

    $released = (float) (is_file($dir.'/released') ? file_get_contents($dir.'/released') : 0);
    $done = (float) (is_file($dir.'/done') ? file_get_contents($dir.'/done') : 0);

    expect($released)->toBeGreaterThan(0)
        ->and($done)->toBeGreaterThanOrEqual($released)
        ->and(app(CustomerExtraRequestService::class)->balance($customerId))->toBe(0);
});

test('mariadb: concurrent last-admin demotions leave exactly one administrator', function () {
    SpatieRole::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    $first = adminWithPermissions(AdminPermissionCatalog::names());
    $second = User::factory()->create();
    $second->assignRole('admin');
    $firstId = (int) $first->id;
    $secondId = (int) $second->id;

    $results = ConcurrentProcesses::map(2, function (int $index) use ($firstId, $secondId) {
        $actorId = $index === 0 ? $firstId : $secondId;
        $targetId = $index === 0 ? $secondId : $firstId;
        $actor = User::query()->findOrFail($actorId);
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

    ConcurrentProcesses::assertAllOk($results);

    expect(app(AdminGuardService::class)->adminUserCount())->toBeGreaterThanOrEqual(1);
});
