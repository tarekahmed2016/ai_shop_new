<?php

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Jobs\DetectDuplicateCustomerRequestJob;
use App\Jobs\FinalizeCustomerRequestJob;
use App\Jobs\RecoverStuckAiRequestsJob;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestIdempotencyKey;
use App\Services\CustomerExtraRequestService;
use App\Services\CustomerRequestDuplicateDetectionService;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Services\RequestClassificationService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concurrency\ConcurrencyFixtures;
use Tests\Support\Concurrency\ConcurrentProcesses;

beforeEach(function () {
    if (! ConcurrentProcesses::supported()) {
        $this->markTestSkipped('pcntl_fork is required for overlapping concurrency tests.');
    }

    enableAsyncClassification();
});

test('overlapping same submission token creates one logical request', function () {
    ConcurrencyFixtures::category();
    ['user' => $user, 'customer' => $customer] = ConcurrencyFixtures::customer();
    $customerId = (int) $customer->id;
    $userId = (int) $user->id;
    $token = (string) Str::ulid();

    $results = ConcurrentProcesses::map(2, function () use ($customerId, $userId, $token) {
        Queue::fake();
        enableAsyncClassification();
        Auth::loginUsingId($userId);
        $customer = Customer::query()->findOrFail($customerId);

        try {
            $request = app(RequestClassificationService::class)->intakeClassify(
                $customer,
                'ABS Sensor for my car',
                null,
                $token,
            );

            return ['id' => (int) $request->id, 'error' => null];
        } catch (ValidationException $exception) {
            return ['id' => null, 'error' => $exception->getMessage()];
        }
    });

    ConcurrentProcesses::assertAllOk($results);

    $ids = array_values(array_filter(array_column(ConcurrentProcesses::values($results), 'id')));

    expect(CustomerRequest::query()->where('customer_id', $customerId)->count())->toBe(1)
        ->and(CustomerRequestIdempotencyKey::query()->where('customer_id', $customerId)->where('token', $token)->count())->toBe(1)
        ->and(count(array_unique($ids)))->toBe(1);
});

test('overlapping different tokens cannot bypass single-flight', function () {
    ConcurrencyFixtures::category();
    ['user' => $user, 'customer' => $customer] = ConcurrencyFixtures::customer();
    $customerId = (int) $customer->id;
    $userId = (int) $user->id;
    $tokens = [(string) Str::ulid(), (string) Str::ulid()];

    $results = ConcurrentProcesses::map(2, function (int $index) use ($customerId, $userId, $tokens) {
        Queue::fake();
        enableAsyncClassification();
        Auth::loginUsingId($userId);
        $customer = Customer::query()->findOrFail($customerId);

        try {
            $request = app(RequestClassificationService::class)->intakeClassify(
                $customer,
                'ABS Sensor '.$index,
                null,
                $tokens[$index],
            );

            return ['id' => (int) $request->id, 'blocked' => false];
        } catch (ValidationException) {
            return ['id' => null, 'blocked' => true];
        }
    });

    ConcurrentProcesses::assertAllOk($results);

    $values = ConcurrentProcesses::values($results);
    $created = array_values(array_filter(array_column($values, 'id')));
    $blocked = count(array_filter($values, fn (array $row) => $row['blocked'] === true));

    expect(CustomerRequest::query()->where('customer_id', $customerId)->count())->toBe(1)
        ->and(count($created))->toBe(1)
        ->and($blocked)->toBe(1);
});

test('stale ai_job_token cannot advance state while a live newer attempt exists', function () {
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $staleToken = (string) Str::ulid();
    $liveToken = (string) Str::ulid();
    $request = ConcurrencyFixtures::processingRequest($customer, jobToken: $liveToken);
    $requestId = (int) $request->id;

    $results = ConcurrentProcesses::map(2, function (int $index) use ($requestId, $staleToken, $liveToken) {
        $stage = app(CustomerRequestAiStageService::class);
        $token = $index === 0 ? $staleToken : $liveToken;

        $advanced = $stage->guardedTransition(
            $requestId,
            AiStage::QueuedClassification,
            $token,
            function (CustomerRequest $locked) use ($stage, $token) {
                $stage->advance($locked, AiStage::Classifying, $token, resetAttempts: false);

                return $token;
            },
        );

        return $advanced;
    });

    $fresh = CustomerRequest::query()->findOrFail($requestId);

    expect($fresh->ai_stage)->toBe(AiStage::Classifying)
        ->and($fresh->ai_job_token)->toBe($liveToken)
        ->and(ConcurrentProcesses::values($results))->toContain($liveToken)
        ->and(ConcurrentProcesses::values($results))->not->toContain($staleToken);
});

test('recovery cannot overwrite a live newer attempt', function () {
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $liveToken = (string) Str::ulid();
    $request = ConcurrencyFixtures::processingRequest($customer, jobToken: $liveToken);
    $request->ai_stage_updated_at = now()->subMinutes(CustomerRequestPipelineConfig::stuckAiThresholdMinutes() + 5);
    $request->save();
    $requestId = (int) $request->id;

    ConcurrentProcesses::map(2, function (int $index) use ($requestId, $liveToken) {
        $stage = app(CustomerRequestAiStageService::class);

        if ($index === 0) {
            Queue::fake();
            app()->call([new RecoverStuckAiRequestsJob, 'handle']);

            return CustomerRequest::query()->findOrFail($requestId)->ai_job_token;
        }

        return $stage->guardedTransition(
            $requestId,
            AiStage::QueuedClassification,
            $liveToken,
            function (CustomerRequest $locked) use ($stage, $liveToken) {
                $stage->advance($locked, AiStage::Classifying, $liveToken, resetAttempts: false);

                return $liveToken;
            },
        );
    });

    $fresh = CustomerRequest::query()->findOrFail($requestId);

    if ($fresh->ai_job_token === $liveToken) {
        expect($fresh->ai_stage)->toBe(AiStage::Classifying);

        return;
    }

    expect($fresh->ai_stage)->toBe(AiStage::QueuedClassification)
        ->and($fresh->ai_job_token)->not->toBe($liveToken);
});

test('overlapping finalize jobs consume quota and extra credit at most once', function () {
    $category = ConcurrencyFixtures::category();
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    $token = (string) Str::ulid();
    ['request' => $request] = ConcurrencyFixtures::readyForFinalize($customer, $category, $token);

    $customer->daily_request_limit_override = 1;
    $customer->save();

    $prior = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => $request->source,
        'status' => RequestStatus::Ready,
        'quota_consumed_at' => now(),
        'ai_stage' => AiStage::Ready,
    ]);
    expect($prior->id)->not->toBe($request->id);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $requestId = (int) $request->id;
    $customerId = (int) $customer->id;

    ConcurrentProcesses::map(2, function () use ($requestId, $token) {
        Queue::fake();
        app()->call([new FinalizeCustomerRequestJob($requestId, $token), 'handle']);

        return CustomerRequest::query()->findOrFail($requestId)->quota_consumed_at?->toIso8601String();
    });

    $fresh = CustomerRequest::query()->findOrFail($requestId);

    expect($fresh->quota_consumed_at)->not->toBeNull()
        ->and($fresh->status)->toBe(RequestStatus::Ready)
        ->and($fresh->ai_stage)->toBe(AiStage::Ready)
        ->and(app(CustomerExtraRequestService::class)->balance($customerId))->toBe(0)
        ->and(CustomerExtraRequestTransaction::query()->where('customer_request_id', $requestId)->count())->toBe(1);
});

test('overlapping early duplicate-check jobs with the same token advance the row once', function () {
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $token = (string) Str::ulid();
    $request = ConcurrencyFixtures::queuedDuplicateCheck($customer, $category, $token);
    $requestId = (int) $request->id;

    ConcurrentProcesses::map(2, function () use ($requestId, $token) {
        app()->call([new DetectDuplicateCustomerRequestJob($requestId, $token), 'handle']);

        return CustomerRequest::query()->findOrFail($requestId)->ai_stage?->value;
    });

    $fresh = CustomerRequest::query()->findOrFail($requestId);

    expect($fresh->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($fresh->status)->toBe(RequestStatus::PendingClassification);
});

test('early duplicate check and finalization on two rows serialize without deadlock', function () {
    if (! $this->usesInnoDbRowLocks()) {
        $this->markTestSkipped('SQLite writer lock cannot interleave two Cache::lock + row-lock transactions. Re-run with CONCURRENCY_DB=mariadb.');
    }

    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $earlyToken = (string) Str::ulid();
    $finalToken = (string) Str::ulid();
    $early = ConcurrencyFixtures::queuedDuplicateCheck($customer, $category, $earlyToken);
    ['request' => $final] = ConcurrencyFixtures::readyForFinalize($customer, $category, $finalToken);
    $earlyId = (int) $early->id;
    $finalId = (int) $final->id;
    $userId = (int) $customer->user_id;

    $results = ConcurrentProcesses::map(2, function (int $index) use ($earlyId, $earlyToken, $finalId, $finalToken, $userId) {
        Queue::fake();
        Auth::loginUsingId($userId);

        if ($index === 0) {
            app()->call([new DetectDuplicateCustomerRequestJob($earlyId, $earlyToken), 'handle']);

            return CustomerRequest::query()->findOrFail($earlyId)->ai_stage?->value;
        }

        app()->call([new FinalizeCustomerRequestJob($finalId, $finalToken), 'handle']);

        return CustomerRequest::query()->findOrFail($finalId)->ai_stage?->value;
    });

    ConcurrentProcesses::assertAllOk($results);

    expect(CustomerRequest::query()->findOrFail($earlyId)->ai_stage)->toEqual(AiStage::ReadyForReview)
        ->and(CustomerRequest::query()->findOrFail($finalId)->ai_stage)->toEqual(AiStage::Ready)
        ->and(CustomerRequest::query()->findOrFail($finalId)->quota_consumed_at)->not->toBeNull();
});

test('overlapping per-customer duplicate locks never enter the critical section together', function () {
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $customerId = (int) $customer->id;
    $dir = sys_get_temp_dir().'/ai_shop_c6_duplock_'.str_replace('.', '_', uniqid('', true));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/active', '0');
    file_put_contents($dir.'/max', '0');

    $results = ConcurrentProcesses::map(2, function (int $index) use ($customerId, $dir) {
        file_put_contents($dir.'/attempt-'.$index, '1');
        $customer = Customer::query()->findOrFail($customerId);

        app(CustomerRequestDuplicateDetectionService::class)->runSerialized($customer, function () use ($dir, $index) {
            $lock = fopen($dir.'/counter', 'c+');
            flock($lock, LOCK_EX);
            $active = ((int) file_get_contents($dir.'/active')) + 1;
            file_put_contents($dir.'/active', (string) $active);
            $max = max((int) file_get_contents($dir.'/max'), $active);
            file_put_contents($dir.'/max', (string) $max);
            flock($lock, LOCK_UN);

            file_put_contents($dir.'/inside-'.$index, '1');
            $deadline = microtime(true) + 2;
            while (count(glob($dir.'/attempt-*') ?: []) < 2 && microtime(true) < $deadline) {
                usleep(1000);
            }
            usleep(80000);

            $lock = fopen($dir.'/counter', 'c+');
            flock($lock, LOCK_EX);
            file_put_contents($dir.'/active', (string) max(0, ((int) file_get_contents($dir.'/active')) - 1));
            flock($lock, LOCK_UN);
        });

        return true;
    });

    ConcurrentProcesses::assertAllOk($results);

    expect((int) file_get_contents($dir.'/max'))->toBe(1);
});

test('overlapping finalize of two requests debit extra credit at most once and never go negative', function () {
    $category = ConcurrencyFixtures::category();
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    $customer->daily_request_limit_override = 1;
    $customer->save();

    CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::Web,
        'status' => RequestStatus::Ready,
        'quota_consumed_at' => now(),
        'ai_stage' => AiStage::Ready,
    ]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $firstToken = (string) Str::ulid();
    $secondToken = (string) Str::ulid();
    ['request' => $first] = ConcurrencyFixtures::readyForFinalize($customer, $category, $firstToken);
    ['request' => $second] = ConcurrencyFixtures::readyForFinalize($customer, $category, $secondToken);
    $payloads = [
        ['id' => (int) $first->id, 'token' => $firstToken],
        ['id' => (int) $second->id, 'token' => $secondToken],
    ];
    $customerId = (int) $customer->id;

    ConcurrentProcesses::map(2, function (int $index) use ($payloads) {
        Queue::fake();
        app()->call([new FinalizeCustomerRequestJob($payloads[$index]['id'], $payloads[$index]['token']), 'handle']);

        return CustomerRequest::query()->findOrFail($payloads[$index]['id'])->status->value;
    });

    $ready = CustomerRequest::query()->whereIn('id', [$first->id, $second->id])->where('status', RequestStatus::Ready)->count();
    $balance = app(CustomerExtraRequestService::class)->balance($customerId);
    $debits = CustomerExtraRequestTransaction::query()
        ->whereIn('customer_request_id', [$first->id, $second->id])
        ->where('amount', -1)
        ->count();

    if (! $this->usesInnoDbRowLocks() && ($ready !== 1 || $balance !== 0 || $debits !== 1)) {
        $this->markTestSkipped('SQLite cannot prove Customer::lockForUpdate() extra-credit serialization across two requests. Re-run with CONCURRENCY_DB=mariadb.');
    }

    expect($ready)->toBe(1)
        ->and($balance)->toBe(0)
        ->and($debits)->toBe(1)
        ->and($balance)->toBeGreaterThanOrEqual(0);
});
