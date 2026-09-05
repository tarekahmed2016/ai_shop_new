<?php

use App\Contracts\AiClassificationProviderInterface;
use App\Contracts\AiDuplicateDetectionProviderInterface;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Exceptions\DuplicateDetectionFailedException;
use App\Jobs\ClassifyCustomerRequestJob;
use App\Jobs\DetectDuplicateCustomerRequestJob;
use App\Jobs\FinalizeCustomerRequestJob;
use App\Jobs\RecoverStuckAiRequestsJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\User;
use App\Services\Classification\FakeClassificationProvider;
use App\Services\CustomerRequestLimitService;
use App\Services\CustomerRequests\CustomerRequestAiStageService;
use App\Services\CustomerRequestService;
use App\Services\DuplicateDetection\FakeDuplicateDetectionProvider;
use App\Services\PlatformSettingService;
use App\Support\CustomerRequests\CustomerRequestMessages;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use App\Support\DuplicateDetection\DuplicateDetectionResult;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Exercises the async AI pipeline mechanics directly (idempotency, race
 * safety, stuck-job recovery, quota timing, legacy compatibility) —
 * complementing RequestClassificationFoundationTest (end-to-end confidence
 * bands) and CustomerRequestDuplicateDetectionTest (duplicate scenarios).
 *
 * Most tests here use Queue::fake() specifically so they can inspect the
 * pipeline *mid-flight* (a thing impossible under the suite's default
 * QUEUE_CONNECTION=sync, which runs every job to completion inline).
 */
beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    enableAsyncClassification();
});

function asyncPipelineCustomer(array $userAttrs = []): array
{
    $user = User::factory()->create($userAttrs);
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ]);

    return compact('user', 'customer');
}

test('ai provider is never invoked on the customer-facing http request, only from the queued job', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($pending->ai_stage)->toBe(AiStage::QueuedClassification)
        ->and(app(AiClassificationProviderInterface::class))->toBeInstanceOf(FakeClassificationProvider::class)
        ->and(app(AiClassificationProviderInterface::class)->lastInput)->toBeNull();

    Queue::assertPushed(ClassifyCustomerRequestJob::class, function (ClassifyCustomerRequestJob $job) use ($pending) {
        return $job->customerRequestId === $pending->id && $job->token === $pending->ai_job_token;
    });
    Queue::assertPushedOn(CustomerRequestPipelineConfig::aiQueue(), ClassifyCustomerRequestJob::class);

    // Now actually run the job (as a worker on the dedicated queue would)
    // and confirm the provider is invoked from there.
    app()->call([new ClassifyCustomerRequestJob($pending->id, $pending->ai_job_token), 'handle']);

    expect(app(AiClassificationProviderInterface::class)->lastInput)->not->toBeNull()
        ->and($pending->fresh()->ai_stage)->not->toBe(AiStage::QueuedClassification);
});

test('double-clicking submit with the same submission token never creates a second row or job', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    Queue::fake();
    $token = (string) Str::ulid();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => $token])
        ->assertRedirect();

    // Simulates a double-click / browser back+resubmit / network retry:
    // identical token, same customer, second POST.
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => $token])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1);
    Queue::assertPushed(ClassifyCustomerRequestJob::class, 1);
});

test('network retry with the same submission token after the pipeline already completed returns the existing row', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();
    $token = (string) Str::ulid();

    // QUEUE_CONNECTION=sync: this already runs the full pipeline inline.
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => $token])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    expect($pending->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and(RequestClassification::query()->where('customer_request_id', $pending->id)->count())->toBe(1);

    // A retried/duplicated POST (e.g. the browser resent the request after
    // a flaky connection) with the exact same token must not run
    // classification — or call the AI provider — a second time.
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => $token])
        ->assertRedirect(route('customer.requests.show', $pending));

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(RequestClassification::query()->where('customer_request_id', $pending->id)->count())->toBe(1);
});

test('a stale ai_job_token makes the classification job a safe no-op', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    Queue::fake();
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $staleToken = $pending->ai_job_token;

    // Simulate a newer retry/recovery having already re-armed the row with
    // a fresh token before the old (stale) job gets to run.
    $freshToken = app(CustomerRequestAiStageService::class)->newToken();
    $pending->ai_job_token = $freshToken;
    $pending->save();

    app()->call([new ClassifyCustomerRequestJob($pending->id, $staleToken), 'handle']);

    expect($pending->fresh()->ai_stage)->toBe(AiStage::QueuedClassification)
        ->and($pending->fresh()->ai_job_token)->toBe($freshToken)
        ->and(app(AiClassificationProviderInterface::class)->lastInput)->toBeNull();
});

test('a stale failed() callback cannot overwrite a newer attempts result', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    // Runs synchronously to completion (ReadyForReview) under the newest
    // token for this attempt lineage.
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    expect($pending->ai_stage)->toBe(AiStage::ReadyForReview);

    // An old worker attempt (already superseded) finally times out and its
    // failed() handler fires late, carrying a token that no longer matches
    // the row.
    $oldJob = new ClassifyCustomerRequestJob($pending->id, 'a-long-gone-superseded-token');
    $oldJob->failed(new RuntimeException('late timeout from an abandoned attempt'));

    expect($pending->fresh()->ai_stage)->toBe(AiStage::ReadyForReview);
});

test('stuck-job recovery redispatches with a fresh token and eventually marks exhausted rows failed', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['customer' => $customer] = asyncPipelineCustomer();

    config(['customer_requests.stuck_ai_threshold_minutes' => 3]);
    config(['customer_requests.stuck_ai_max_recovery_attempts' => 1]);

    $stuck = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => AiStage::Classifying,
        'ai_job_token' => 'dead-worker-token',
        'ai_stage_updated_at' => now()->subMinutes(10),
        'ai_attempts' => 0,
    ]);

    Queue::fake();
    app()->call([new RecoverStuckAiRequestsJob, 'handle']);

    $stuck->refresh();
    expect($stuck->ai_stage)->toBe(AiStage::QueuedClassification)
        ->and($stuck->ai_job_token)->not->toBe('dead-worker-token')
        ->and($stuck->ai_attempts)->toBe(1);

    Queue::assertPushed(ClassifyCustomerRequestJob::class, function (ClassifyCustomerRequestJob $job) use ($stuck) {
        return $job->customerRequestId === $stuck->id && $job->token === $stuck->ai_job_token;
    });

    // Push it past the max attempts ceiling: the next sweep must give up
    // and mark it Failed instead of redispatching forever.
    $stuck->ai_stage_updated_at = now()->subMinutes(10);
    $stuck->save();

    Queue::fake();
    app()->call([new RecoverStuckAiRequestsJob, 'handle']);

    expect($stuck->fresh()->ai_stage)->toBe(AiStage::Failed);
    Queue::assertNotPushed(ClassifyCustomerRequestJob::class);
});

test('stuck-job recovery of a finalization-pipeline row returns it to review after max attempts', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['customer' => $customer] = asyncPipelineCustomer();

    config(['customer_requests.stuck_ai_threshold_minutes' => 3]);
    config(['customer_requests.stuck_ai_max_recovery_attempts' => 1]);

    $stuck = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => AiStage::CheckingFinalDuplicate,
        'ai_job_token' => 'dead-finalize-token',
        'ai_stage_updated_at' => now()->subMinutes(10),
        'ai_attempts' => 0,
    ]);

    Queue::fake();
    app()->call([new RecoverStuckAiRequestsJob, 'handle']);

    expect($stuck->fresh()->ai_stage)->toBe(AiStage::QueuedFinalDuplicateCheck);
    Queue::assertPushed(FinalizeCustomerRequestJob::class);

    $stuck->refresh();
    $stuck->ai_stage_updated_at = now()->subMinutes(10);
    $stuck->save();

    Queue::fake();
    app()->call([new RecoverStuckAiRequestsJob, 'handle']);

    expect($stuck->fresh()->ai_stage)->toBe(AiStage::ReadyForReview);
    Queue::assertNotPushed(FinalizeCustomerRequestJob::class);
});

test('stuck-job recovery re-checks staleness under the lock so it cannot clobber a row a live worker just advanced', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['customer' => $customer] = asyncPipelineCustomer();

    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => AiStage::Classifying,
        'ai_job_token' => 'live-worker-token',
        'ai_stage_updated_at' => now()->subMinutes(10),
        'ai_attempts' => 0,
    ]);

    // A decorator that, the instant the sweep takes its row lock on this
    // request (its *first* guardedTransition call for it), injects a
    // "live worker just finished" write in between — reproducing the
    // exact race the sweep's re-check-under-the-lock is meant to defend
    // against, without needing real concurrency.
    $real = app(CustomerRequestAiStageService::class);
    $targetId = $request->id;

    $spy = new class($real, $targetId) extends CustomerRequestAiStageService
    {
        private bool $fired = false;

        public function __construct(private CustomerRequestAiStageService $real, private int $targetId) {}

        public function newToken(): string
        {
            return $this->real->newToken();
        }

        public function guardedTransition(int $customerRequestId, AiStage|array|null $acceptedStages, ?string $expectedToken, Closure $mutate): mixed
        {
            if (! $this->fired && $customerRequestId === $this->targetId && is_array($acceptedStages)) {
                $this->fired = true;

                $this->real->guardedTransition(
                    $customerRequestId,
                    AiStage::Classifying,
                    'live-worker-token',
                    function (CustomerRequest $locked) {
                        $this->real->advance($locked, AiStage::ReadyForReview, null);
                    },
                );
            }

            return $this->real->guardedTransition($customerRequestId, $acceptedStages, $expectedToken, $mutate);
        }

        public function advance(CustomerRequest $request, AiStage $stage, ?string $token, bool $resetAttempts = true): void
        {
            $this->real->advance($request, $stage, $token, $resetAttempts);
        }
    };

    app()->instance(CustomerRequestAiStageService::class, $spy);

    Queue::fake();
    app()->call([new RecoverStuckAiRequestsJob, 'handle']);

    expect($request->fresh()->ai_stage)->toBe(AiStage::ReadyForReview);
    Queue::assertNotPushed(ClassifyCustomerRequestJob::class);
});

test('expired stale ready-for-review and failed rows are cancelled by the sweep', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['customer' => $customer] = asyncPipelineCustomer();
    config(['customer_requests.open_attempt_ttl_hours' => 48]);

    $abandoned = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => AiStage::ReadyForReview,
        'ai_stage_updated_at' => now()->subHours(72),
    ]);

    app()->call([new RecoverStuckAiRequestsJob, 'handle']);

    expect($abandoned->fresh()->ai_stage)->toBe(AiStage::Expired)
        ->and($abandoned->fresh()->status)->toBe(RequestStatus::Cancelled);
});

test('quota is never counted while a request is queued, classifying, or in review', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    Queue::fake(); // freeze at QueuedClassification — never runs the job

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($pending->quota_consumed_at)->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(0);

    // Now let it run all the way to ReadyForReview — still untouched.
    app()->call([new ClassifyCustomerRequestJob($pending->id, $pending->ai_job_token), 'handle']);
    $pending->refresh();

    if ($pending->ai_stage === AiStage::QueuedDuplicateCheck) {
        app()->call([new DetectDuplicateCustomerRequestJob($pending->id, $pending->ai_job_token), 'handle']);
        $pending->refresh();
    }

    expect($pending->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($pending->quota_consumed_at)->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(0);
});

test('confirming twice with the same submission token consumes quota exactly once', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $classification = RequestClassification::query()->latest('id')->first();
    $confirmToken = (string) Str::ulid();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => $confirmToken,
        ])
        ->assertRedirect();

    $request = $classification->customerRequest->fresh();
    $firstConsumedAt = $request->quota_consumed_at;
    expect($firstConsumedAt)->not->toBeNull()
        ->and($request->ai_stage)->toBe(AiStage::Ready);

    Queue::fake();

    // Same confirm click resent (double submit / network retry) with the
    // identical submission_token: must be a pure no-op — no second
    // FinalizeCustomerRequestJob, no change to quota_consumed_at.
    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => $confirmToken,
        ])
        ->assertRedirect();

    Queue::assertNotPushed(FinalizeCustomerRequestJob::class);
    expect($request->fresh()->quota_consumed_at->eq($firstConsumedAt))->toBeTrue()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);
});

test('delayed classify retry with the original token after confirm does not create a second request or AI job', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $classifyToken = (string) Str::ulid();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => $classifyToken,
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    expect($request->ai_stage)->toBe(AiStage::ReadyForReview);

    $classifier = app(AiClassificationProviderInterface::class);
    expect($classifier)->toBeInstanceOf(FakeClassificationProvider::class);
    $classifyCalls = $classifier->calls;
    $classificationCount = RequestClassification::query()->count();

    $classification = RequestClassification::query()->where('customer_request_id', $request->id)->latest('id')->first();
    $confirmToken = (string) Str::ulid();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => $confirmToken,
        ])
        ->assertRedirect();

    expect($request->fresh()->ai_stage)->toBe(AiStage::Ready)
        ->and($request->fresh()->submission_token)->toBe($confirmToken);

    $duplicateProvider = app(AiDuplicateDetectionProviderInterface::class);
    expect($duplicateProvider)->toBeInstanceOf(FakeDuplicateDetectionProvider::class);
    $duplicateCalls = $duplicateProvider->calls;

    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => $classifyToken,
        ])
        ->assertRedirect(route('customer.requests.show', $request));

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(RequestClassification::query()->count())->toBe($classificationCount)
        ->and($classifier->calls)->toBe($classifyCalls)
        ->and($duplicateProvider->calls)->toBe($duplicateCalls);

    Queue::assertNotPushed(ClassifyCustomerRequestJob::class);
    Queue::assertNotPushed(DetectDuplicateCustomerRequestJob::class);
    Queue::assertNotPushed(FinalizeCustomerRequestJob::class);
});

test('delayed confirm retry with the original token does not dispatch a second finalization even after submission_token is overwritten', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    $classification = RequestClassification::query()->latest('id')->first();
    $confirmToken = (string) Str::ulid();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => $confirmToken,
        ])
        ->assertRedirect();

    $request = $classification->customerRequest->fresh();
    $consumedAt = $request->quota_consumed_at;
    expect($consumedAt)->not->toBeNull()
        ->and($request->ai_stage)->toBe(AiStage::Ready);

    $request->submission_token = (string) Str::ulid();
    $request->save();

    $duplicateProvider = app(AiDuplicateDetectionProviderInterface::class);
    expect($duplicateProvider)->toBeInstanceOf(FakeDuplicateDetectionProvider::class);
    $duplicateCalls = $duplicateProvider->calls;

    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => $confirmToken,
        ])
        ->assertRedirect(route('customer.requests.show', $request));

    Queue::assertNotPushed(FinalizeCustomerRequestJob::class);
    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($request->fresh()->quota_consumed_at->eq($consumedAt))->toBeTrue()
        ->and($duplicateProvider->calls)->toBe($duplicateCalls)
        ->and($request->fresh()->ai_stage)->toBe(AiStage::Ready);
});

test('retrying after a classification failure never double-counts against quota', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    // Built directly (rather than driven through a FORCE_FAIL classify
    // call) because retry *appends* additional_details to request_text
    // rather than replacing it — going through FORCE_FAIL for real would
    // leave the trigger token in the text forever and the retry would
    // fail again too. What matters for this test is only: a row that
    // failed classification, with quota untouched, that then succeeds on
    // retry — and quota must still only be consumed once, at confirm.
    $pending = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'source' => Source::Web,
        'ai_stage' => AiStage::Failed,
        'ai_stage_updated_at' => now(),
        'ai_job_token' => null,
        'submission_token' => (string) Str::ulid(),
        'request_text' => 'a car part',
        'quota_consumed_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'ABS Sensor for a Toyota Camry',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    // The retry itself never touched quota; only a subsequent confirm can.
    expect($pending->fresh()->quota_consumed_at)->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(0);

    $classification = RequestClassification::query()->where('customer_request_id', $pending->id)->latest('id')->first();
    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect($pending->fresh()->quota_consumed_at)->not->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);
});

test('customer refresh mid pipeline resumes polling from the current stage', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    Queue::fake(); // freeze mid-pipeline
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    // Simulates the browser tab being closed and reopened / a hard
    // refresh: a brand-new GET, no client-side state carried over.
    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.ai_stage', 'queued_classification')
            ->where('status.poll', true)
        );

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $pending))
        ->assertOk()
        ->assertJson(['ai_stage' => 'queued_classification', 'poll' => true]);

    // Once it reaches a terminal stage, polling must stop being requested.
    app()->call([new ClassifyCustomerRequestJob($pending->id, $pending->ai_job_token), 'handle']);
    $pending->refresh();
    if ($pending->ai_stage === AiStage::QueuedDuplicateCheck) {
        app()->call([new DetectDuplicateCustomerRequestJob($pending->id, $pending->ai_job_token), 'handle']);
    }

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $pending))
        ->assertOk()
        ->assertJson(['ai_stage' => 'ready_for_review', 'poll' => false]);
});

test('the classification status endpoint enforces per-customer ownership', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $userA, 'customer' => $customerA] = asyncPipelineCustomer(['email' => 'pipeline-a@example.com']);
    ['user' => $userB] = asyncPipelineCustomer(['email' => 'pipeline-b@example.com']);

    $this->actingAs($userA)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('customer_id', $customerA->id)->first();

    $this->actingAs($userB)
        ->get(route('customer.requests.classification-status', $request))
        ->assertNotFound();

    $this->actingAs($userB)
        ->get(route('customer.requests.show', $request))
        ->assertNotFound();

    $this->actingAs($userA)
        ->get(route('customer.requests.classification-status', $request))
        ->assertOk();
});

test('a legacy pending row without ai_stage can still be resumed and confirmed', function () {
    $category = Category::factory()->create(['name_en' => 'Auto Spare Parts', 'status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $legacy = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => null,
        'ai_stage_updated_at' => null,
        'category_id' => null,
        'request_text' => 'legacy ABS Sensor request from before the pipeline existed',
    ]);
    $legacyClassification = RequestClassification::factory()->create([
        'customer_request_id' => $legacy->id,
        'status' => ClassificationStatus::Suggested,
        'suggested_category_id' => $category->id,
        'confidence' => 0.95,
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $legacyClassification), [
            'category_id' => $category->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect(route('customer.requests.show', $legacy));

    expect($legacy->fresh()->status)->toBe(RequestStatus::Ready)
        ->and($legacy->fresh()->category_id)->toBe($category->id)
        ->and($legacy->fresh()->ai_stage)->toBe(AiStage::Ready)
        ->and($legacy->fresh()->quota_consumed_at)->not->toBeNull();
});

test('a legacy pending row can be resumed via retry and graduates into the async pipeline', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $legacy = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => null,
        'ai_stage_updated_at' => null,
        'category_id' => null,
        'request_text' => 'legacy request needing more detail',
    ]);
    RequestClassification::factory()->create([
        'customer_request_id' => $legacy->id,
        'status' => ClassificationStatus::NeedsReview,
        'needs_more_information' => true,
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $legacy), [
            'additional_details' => 'ABS Sensor for a Toyota Camry 2018',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect(route('customer.requests.show', $legacy));

    // Graduated: it now has a real ai_stage and was processed by the
    // async pipeline (QUEUE_CONNECTION=sync ran it inline).
    expect($legacy->fresh()->ai_stage)->not->toBeNull()
        ->and(RequestClassification::query()->where('customer_request_id', $legacy->id)->count())->toBe(2);
});

test('a legacy confirm-time duplicate keeps the row cancelled instead of deleting it', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $first = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::Ready,
        'source' => Source::Web,
        'category_id' => $category->id,
        'normalized_request_json' => ['item' => 'abs sensor', 'summary' => 'abs sensor front'],
    ]);

    $legacy = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => null,
        'ai_stage_updated_at' => null,
        'category_id' => null,
        'request_text' => 'legacy duplicate of the first request',
    ]);
    $legacyClassification = RequestClassification::factory()->create([
        'customer_request_id' => $legacy->id,
        'status' => ClassificationStatus::Suggested,
        'suggested_category_id' => $category->id,
        'confidence' => 0.95,
    ]);

    $duplicateProvider = app(AiDuplicateDetectionProviderInterface::class);
    $duplicateProvider->reset();
    $duplicateProvider->queue(new DuplicateDetectionResult(
        isDuplicate: true,
        matchedRequestId: $first->id,
        confidence: 0.98,
        reasonCode: 'same_commercial_need',
    ));

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $legacyClassification), [
            'category_id' => $category->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect(route('customer.requests.show', $legacy));

    expect(CustomerRequest::query()->find($legacy->id))->not->toBeNull() // kept, not deleted
        ->and($legacy->fresh()->ai_stage)->toBe(AiStage::DuplicateBlocked)
        ->and($legacy->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($legacy->fresh()->duplicate_of_customer_request_id)->toBe($first->id);
});

test('admin-created requests are never touched by the async pipeline', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $category = Category::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'category_id' => $category->public_id,
            'request_text' => 'Admin created directly',
            'status' => RequestStatus::Ready->value,
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('request_text', 'Admin created directly')->first();

    expect($request->ai_stage)->toBeNull()
        ->and($request->submission_token)->toBeNull()
        ->and($request->quota_consumed_at)->toBeNull();
});

test('confirm http handler never invokes an ai provider', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user] = asyncPipelineCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $classification = RequestClassification::query()->latest('id')->first();
    $duplicateProvider = app(AiDuplicateDetectionProviderInterface::class);
    $duplicateProvider->reset();
    $classifyCallsBefore = app(AiClassificationProviderInterface::class)->lastInput;

    Http::preventStrayRequests();
    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect($duplicateProvider->calls)->toBe(0)
        ->and($duplicateProvider->lastInput)->toBeNull()
        ->and(app(AiClassificationProviderInterface::class)->lastInput)->toBe($classifyCallsBefore);

    Queue::assertPushed(FinalizeCustomerRequestJob::class);
    Http::assertNothingSent();
});

test('duplicate-check provider failure fails open into ready_for_review', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $duplicateProvider = app(AiDuplicateDetectionProviderInterface::class);
    $duplicateProvider->reset();
    $duplicateProvider->handler = function () {
        throw new DuplicateDetectionFailedException('forced timeout');
    };

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($pending->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($pending->quota_consumed_at)->toBeNull();
});

test('final duplicate check blocks confirmation without consuming quota', function () {
    $category = Category::factory()->create(['name_en' => 'Auto Spare Parts', 'status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor first UNIQUE_A', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $first = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $firstClassification = RequestClassification::query()->where('customer_request_id', $first->id)->first();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $firstClassification), [
            'category_id' => $firstClassification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect($first->fresh()->status)->toBe(RequestStatus::Ready);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor second UNIQUE_B', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $second = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $secondClassification = RequestClassification::query()->where('customer_request_id', $second->id)->first();

    $duplicateProvider = app(AiDuplicateDetectionProviderInterface::class);
    $duplicateProvider->reset();
    $duplicateProvider->queue(new DuplicateDetectionResult(
        isDuplicate: true,
        matchedRequestId: (int) $first->id,
        confidence: 0.99,
        reasonCode: 'same_commercial_need',
    ));

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $secondClassification), [
            'category_id' => $secondClassification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect($second->fresh()->ai_stage)->toBe(AiStage::DuplicateBlocked)
        ->and($second->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($second->fresh()->quota_consumed_at)->toBeNull()
        ->and($second->fresh()->duplicate_of_customer_request_id)->toBe($first->id)
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);
});

test('quota exhausted at finalization returns the row to review without consuming credit', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor first UNIQUE_A', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $firstClassification = RequestClassification::query()->where('customer_request_id', $first->id)->first();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor second UNIQUE_B', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $second = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $secondClassification = RequestClassification::query()->where('customer_request_id', $second->id)->first();

    expect($first->fresh()->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($second->fresh()->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(0);

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $firstClassification), [
            'category_id' => $firstClassification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect($first->fresh()->status)->toBe(RequestStatus::Ready)
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $secondClassification), [
            'category_id' => $secondClassification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    $second->refresh();
    expect($second->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($second->ai_stage_reason)->toBe('quota_exhausted_at_finalization')
        ->and($second->quota_consumed_at)->toBeNull()
        ->and($second->status)->toBe(RequestStatus::PendingClassification)
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $second))
        ->assertOk()
        ->assertJson([
            'ai_stage' => 'ready_for_review',
            'quota_exhausted' => true,
        ]);
});

test('status endpoint returns a cached duplicate notice after the pending row is gone', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $matched = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::Ready,
        'source' => Source::Web,
        'category_id' => $category->id,
        'quota_consumed_at' => now(),
    ]);

    $pending = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'source' => Source::Web,
        'ai_stage' => AiStage::CheckingDuplicate,
        'quota_consumed_at' => null,
    ]);

    app(CustomerRequestService::class)->markDuplicateBlocked($pending, (int) $matched->id);
    $publicId = $pending->public_id;
    $pending->delete();

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $publicId))
        ->assertOk()
        ->assertJson([
            'ai_stage' => 'duplicate_blocked',
            'poll' => false,
            'duplicate_of_request_public_id' => $matched->public_id,
            'message' => CustomerRequestMessages::duplicateRequest(),
        ]);
});

test('status endpoint returns a graceful missing payload when both the row and cache notice are gone', function () {
    ['user' => $user] = asyncPipelineCustomer();
    $ghost = (string) Str::ulid();

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $ghost))
        ->assertOk()
        ->assertJson([
            'request_public_id' => $ghost,
            'poll' => false,
            'message' => CustomerRequestMessages::requestNoLongerAvailable(),
        ]);
});

test('classification failure never consumes quota or extra credit', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'FORCE_FAIL boom', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($pending->ai_stage)->toBe(AiStage::Failed)
        ->and($pending->quota_consumed_at)->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(0);
});

test('duplicate notice cache miss after expiry still returns a graceful status payload', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user, 'customer' => $customer] = asyncPipelineCustomer();

    $matched = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::Ready,
        'source' => Source::Web,
        'category_id' => $category->id,
        'quota_consumed_at' => now(),
    ]);

    $pending = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => RequestStatus::PendingClassification,
        'source' => Source::Web,
        'ai_stage' => AiStage::CheckingDuplicate,
        'quota_consumed_at' => null,
    ]);

    app(CustomerRequestService::class)->markDuplicateBlocked($pending, (int) $matched->id);
    $publicId = $pending->public_id;

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $publicId))
        ->assertOk()
        ->assertJson(['ai_stage' => 'duplicate_blocked']);

    Cache::flush();

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $publicId))
        ->assertOk()
        ->assertJson([
            'ai_stage' => 'duplicate_blocked',
            'duplicate_of_request_public_id' => $matched->public_id,
            'message' => CustomerRequestMessages::duplicateRequest(),
        ]);

    $pending->delete();
    Cache::flush();

    $this->actingAs($user)
        ->get(route('customer.requests.classification-status', $publicId))
        ->assertOk()
        ->assertJson([
            'request_public_id' => $publicId,
            'poll' => false,
            'ai_stage' => null,
            'message' => CustomerRequestMessages::requestNoLongerAvailable(),
        ]);
});

test('ai jobs honour a configured queue name instead of a hardcoded one', function () {
    config(['customer_requests.ai_queue' => 'custom-ai-queue']);
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user] = asyncPipelineCustomer();

    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    Queue::assertPushedOn('custom-ai-queue', ClassifyCustomerRequestJob::class);
    Queue::assertNotPushed(fn (ClassifyCustomerRequestJob $job) => $job->queue === 'ai-processing');
});

test('stuck recovery is scheduled as an artisan command not an ai-queue job', function () {
    $minutes = CustomerRequestPipelineConfig::stuckAiRecoveryEveryMinutes();
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduled) => str_contains((string) $scheduled->command, 'customer-requests:recover-stuck-ai'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/'.$minutes.' * * * *');

    $this->artisan('customer-requests:recover-stuck-ai')->assertSuccessful();
});
