<?php

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Enums\Users\Status as UserStatus;
use App\Jobs\DispatchMatchedRequestNotifications;
use App\Jobs\FinalizeCustomerRequestJob;
use App\Jobs\MatchCustomerRequestJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantRequestMatch;
use App\Models\MerchantUser;
use App\Models\RequestClassification;
use App\Models\RequestMatch;
use App\Models\User;
use App\Notifications\MatchedCustomerRequestNotification;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

/**
 * @return array{
 *     category: Category,
 *     merchantA: Merchant,
 *     merchantB: Merchant,
 *     request: CustomerRequest,
 *     user: User
 * }
 */
function asyncMatchingSetup(RequestStatus $status = RequestStatus::Ready): array
{
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $merchantA = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    $merchantB = Merchant::factory()->create(['status' => MerchantStatus::Active]);
    MerchantCategory::factory()->create(['merchant_id' => $merchantA->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchantB->id, 'category_id' => $category->id]);

    $user = User::factory()->create(['status' => UserStatus::Active]);
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchantA->id,
        'status' => MembershipStatus::Active,
        'role' => Role::Owner,
    ]);

    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => $status,
        'request_text' => 'Need an ABS sensor',
    ]);

    return compact('category', 'merchantA', 'merchantB', 'request', 'user');
}

function runMatchingJob(int $customerRequestId): void
{
    app()->call([new MatchCustomerRequestJob($customerRequestId), 'handle']);
}

test('matching job creates one live match and one history row per eligible merchant', function () {
    ['request' => $request, 'merchantA' => $merchantA, 'merchantB' => $merchantB] = asyncMatchingSetup();

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->pluck('merchant_id')->sort()->values()->all())
        ->toEqual(collect([$merchantA->id, $merchantB->id])->sort()->values()->all())
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and($request->fresh()->matching_completed_at)->not->toBeNull();
});

test('duplicate matching job execution does not duplicate live matches, history, or notifications', function () {
    Notification::fake();
    ['request' => $request, 'user' => $user] = asyncMatchingSetup();

    runMatchingJob((int) $request->id);
    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2);

    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);
});

test('concurrent matching jobs serialize on the request row and do not duplicate rows', function () {
    ['request' => $request] = asyncMatchingSetup();

    $first = new MatchCustomerRequestJob((int) $request->id);
    $second = new MatchCustomerRequestJob((int) $request->id);
    app()->call([$first, 'handle']);
    app()->call([$second, 'handle']);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2);

    $merchantId = (int) RequestMatch::query()->where('customer_request_id', $request->id)->value('merchant_id');
    $now = now();

    expect(fn () => RequestMatch::query()->insert([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchantId,
        'status' => 1,
        'matched_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => MerchantRequestMatch::query()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchantId,
        'matched_at' => $now,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('merchant that becomes ineligible before the job runs is not live-matched', function () {
    ['request' => $request, 'merchantB' => $merchantB] = asyncMatchingSetup();
    $merchantB->status = MerchantStatus::Inactive;
    $merchantB->save();

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(RequestMatch::query()->where('merchant_id', $merchantB->id)->exists())->toBeFalse()
        ->and(MerchantRequestMatch::query()->where('merchant_id', $merchantB->id)->exists())->toBeFalse();
});

test('eligibility change after matches exist is applied only on the next sync', function () {
    ['request' => $request, 'merchantB' => $merchantB] = asyncMatchingSetup();

    runMatchingJob((int) $request->id);
    expect(RequestMatch::query()->where('merchant_id', $merchantB->id)->exists())->toBeTrue();

    $merchantB->status = MerchantStatus::Inactive;
    $merchantB->save();

    expect(RequestMatch::query()->where('merchant_id', $merchantB->id)->exists())->toBeTrue()
        ->and(MerchantRequestMatch::query()->where('merchant_id', $merchantB->id)->count())->toBe(1);

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('merchant_id', $merchantB->id)->exists())->toBeFalse()
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(MerchantRequestMatch::query()->where('merchant_id', $merchantB->id)->count())->toBe(1);
});

test('cancelled request before matching creates no live matches and no history', function () {
    ['request' => $request] = asyncMatchingSetup();
    $request->status = RequestStatus::Cancelled;
    $request->save();

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0);
});

test('deleted request before matching is a no-op', function () {
    ['request' => $request] = asyncMatchingSetup();
    $id = (int) $request->id;
    $request->delete();

    runMatchingJob($id);

    expect(RequestMatch::query()->where('customer_request_id', $id)->count())->toBe(0)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $id)->count())->toBe(0);
});

test('matching job dispatches notification fan-out after the matching transaction commits', function () {
    Queue::fake();
    ['request' => $request] = asyncMatchingSetup();

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2);

    $matchIds = RequestMatch::query()
        ->where('customer_request_id', $request->id)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    Queue::assertPushed(DispatchMatchedRequestNotifications::class, 1);
    Queue::assertPushed(DispatchMatchedRequestNotifications::class, function (DispatchMatchedRequestNotifications $job) use ($request, $matchIds) {
        return $job->customerRequestId === (int) $request->id
            && $job->matchIds === $matchIds;
    });
});

test('matching job retry does not duplicate recipient notifications', function () {
    Notification::fake();
    ['request' => $request, 'user' => $user] = asyncMatchingSetup();

    $job = new MatchCustomerRequestJob((int) $request->id);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);
    Notification::assertSentTimes(MatchedCustomerRequestNotification::class, 1);
});

test('matching failure does not undo Ready status', function () {
    ['request' => $request] = asyncMatchingSetup();
    expect($request->status)->toBe(RequestStatus::Ready);

    $this->mock(RequestMatchingService::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andThrow(new RuntimeException('matching exploded'));
    });

    $job = new MatchCustomerRequestJob((int) $request->id);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);

    $job->failed(new RuntimeException('matching exploded'));

    expect($request->fresh()->status)->toBe(RequestStatus::Ready)
        ->and($request->fresh()->matching_completed_at)->toBeNull()
        ->and($request->fresh()->matching_last_attempt_at)->not->toBeNull()
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0);
});

test('async AI finalization dispatches matching on the default queue without creating matches inline', function () {
    enableAsyncClassification();
    ['category' => $category, 'merchantA' => $merchantA, 'merchantB' => $merchantB] = asyncMatchingSetup();

    $customer = Customer::factory()->create();
    $token = (string) Str::ulid();
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => null,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => AiStage::QueuedFinalDuplicateCheck,
        'ai_job_token' => $token,
        'confirmed_category_id' => $category->id,
        'request_text' => 'ABS sensor front',
        'normalized_request_json' => [
            'item' => 'abs sensor',
            'summary' => 'abs sensor front',
        ],
    ]);
    $classification = RequestClassification::factory()->create([
        'customer_request_id' => $request->id,
        'suggested_category_id' => $category->id,
        'status' => ClassificationStatus::Suggested,
        'confidence' => 0.95,
        'detected_item' => 'abs sensor',
    ]);
    $request->confirmed_classification_id = $classification->id;
    $request->save();

    Queue::fake();

    app()->call([new FinalizeCustomerRequestJob((int) $request->id, $token), 'handle']);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Ready)
        ->and($fresh->ai_stage)->toBe(AiStage::Ready)
        ->and($fresh->category_id)->toBe($category->id)
        ->and($fresh->quota_consumed_at)->not->toBeNull()
        ->and($fresh->matching_completed_at)->toBeNull()
        ->and($fresh->matching_last_attempt_at)->toBeNull()
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0);

    Queue::assertPushed(MatchCustomerRequestJob::class, 1);
    Queue::assertPushed(MatchCustomerRequestJob::class, function (MatchCustomerRequestJob $job) use ($request) {
        return $job->customerRequestId === (int) $request->id
            && ($job->queue === null || $job->queue === 'default');
    });
    Queue::assertNotPushed(DispatchMatchedRequestNotifications::class);

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(RequestMatch::query()->whereIn('merchant_id', [$merchantA->id, $merchantB->id])->count())->toBe(2)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and($request->fresh()->matching_completed_at)->not->toBeNull();
});

test('lost matching dispatch after Ready is recovered without duplicating matches or notifications', function () {
    Notification::fake();
    enableAsyncClassification();
    ['category' => $category, 'user' => $user, 'request' => $seeded] = asyncMatchingSetup();
    $seeded->matching_completed_at = now();
    $seeded->save();

    $customer = Customer::factory()->create();
    $token = (string) Str::ulid();
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => null,
        'status' => RequestStatus::PendingClassification,
        'ai_stage' => AiStage::QueuedFinalDuplicateCheck,
        'ai_job_token' => $token,
        'confirmed_category_id' => $category->id,
        'request_text' => 'ABS sensor front',
        'normalized_request_json' => [
            'item' => 'abs sensor',
            'summary' => 'abs sensor front',
        ],
    ]);
    $classification = RequestClassification::factory()->create([
        'customer_request_id' => $request->id,
        'suggested_category_id' => $category->id,
        'status' => ClassificationStatus::Suggested,
        'confidence' => 0.95,
        'detected_item' => 'abs sensor',
    ]);
    $request->confirmed_classification_id = $classification->id;
    $request->save();

    Queue::fake([MatchCustomerRequestJob::class]);
    app()->call([new FinalizeCustomerRequestJob((int) $request->id, $token), 'handle']);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Ready)
        ->and($fresh->matching_completed_at)->toBeNull()
        ->and($fresh->matching_last_attempt_at)->toBeNull()
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0);

    Queue::assertPushed(MatchCustomerRequestJob::class, 1);

    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();

    Queue::assertPushed(MatchCustomerRequestJob::class, 2);

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and($request->fresh()->matching_completed_at)->not->toBeNull()
        ->and($request->fresh()->status)->toBe(RequestStatus::Ready);

    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2);
    Notification::assertSentToTimes($user, MatchedCustomerRequestNotification::class, 1);

    Queue::fake([MatchCustomerRequestJob::class]);
    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('successful matching with zero eligible merchants marks matching completed', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'Need an ABS sensor',
    ]);

    runMatchingJob((int) $request->id);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0)
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Ready)
        ->and($request->fresh()->matching_completed_at)->not->toBeNull();
});

test('matching job failure leaves Ready recoverable after the stale window', function () {
    ['request' => $request] = asyncMatchingSetup();

    $this->mock(RequestMatchingService::class, function ($mock) {
        $mock->shouldReceive('sync')->once()->andThrow(new RuntimeException('matching exploded'));
    });

    $job = new MatchCustomerRequestJob((int) $request->id);
    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('matching exploded'));

    expect($request->fresh()->status)->toBe(RequestStatus::Ready)
        ->and($request->fresh()->matching_completed_at)->toBeNull()
        ->and($request->fresh()->matching_last_attempt_at)->not->toBeNull();

    Queue::fake([MatchCustomerRequestJob::class]);
    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();
    Queue::assertNothingPushed();

    $this->travel(CustomerRequestPipelineConfig::matchingRecoveryStaleMinutes() + 1)->minutes();
    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();
    Queue::assertPushed(MatchCustomerRequestJob::class, 1);
});

test('duplicate recovery sweeps dispatch matching only once while an attempt is fresh', function () {
    ['request' => $request] = asyncMatchingSetup();

    Queue::fake([MatchCustomerRequestJob::class]);
    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();
    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();

    Queue::assertPushed(MatchCustomerRequestJob::class, 1);
    Queue::assertPushed(MatchCustomerRequestJob::class, function (MatchCustomerRequestJob $job) use ($request) {
        return $job->customerRequestId === (int) $request->id;
    });
    expect($request->fresh()->matching_last_attempt_at)->not->toBeNull()
        ->and($request->fresh()->matching_completed_at)->toBeNull();
});

test('completed matching is not recovered again', function () {
    ['request' => $request] = asyncMatchingSetup();
    runMatchingJob((int) $request->id);
    expect($request->fresh()->matching_completed_at)->not->toBeNull();

    Queue::fake([MatchCustomerRequestJob::class]);
    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('pending matching recovery is scheduled independently of the ai queue', function () {
    $minutes = CustomerRequestPipelineConfig::matchingRecoveryEveryMinutes();
    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduled) => str_contains((string) $scheduled->command, 'customer-requests:recover-pending-matching'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/'.$minutes.' * * * *');

    $this->artisan('customer-requests:recover-pending-matching')->assertSuccessful();
});
