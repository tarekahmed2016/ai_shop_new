<?php

use App\Contracts\AiDuplicateDetectionProviderInterface;
use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Exceptions\DuplicateDetectionFailedException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantRequestMatch;
use App\Models\PlatformSetting;
use App\Models\RequestClassification;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use App\Services\CustomerRequestDuplicateDetectionService;
use App\Services\CustomerRequestLimitService;
use App\Services\DuplicateDetection\FakeDuplicateDetectionProvider;
use App\Services\PlatformSettingService;
use App\Support\CustomerRequests\CustomerRequestMessages;
use App\Support\DuplicateDetection\DuplicateDetectionResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * QUEUE_CONNECTION=sync in phpunit.xml runs ClassifyCustomerRequestJob,
 * DetectDuplicateCustomerRequestJob (early check, right after
 * classification) and FinalizeCustomerRequestJob (final check, at confirm
 * time) inline, before the triggering HTTP response returns. So a single
 * classifyRequest() call already exercises the *early* duplicate check
 * end-to-end, and confirmRequest() exercises the *final* one.
 *
 * Duplicate-blocked rows are never deleted (see
 * CustomerRequestService::markDuplicateBlocked()) — they are kept with
 * ai_stage=DuplicateBlocked / status=Cancelled so the customer's own show
 * page can present a graceful "this looks like an existing request"
 * outcome. That means CustomerRequest row counts grow by one per duplicate
 * attempt; tests here assert on status/ai_stage rather than raw counts
 * where that distinction matters.
 */
beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Category::factory()->create(['name_en' => 'Auto Spare Parts']);
    duplicateProvider()->reset();
    enableAsyncClassification();
});

function duplicateProvider(): FakeDuplicateDetectionProvider
{
    $provider = app(AiDuplicateDetectionProviderInterface::class);
    expect($provider)->toBeInstanceOf(FakeDuplicateDetectionProvider::class);

    return $provider;
}

function duplicateCustomer(array $userAttrs = []): array
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

function queueDuplicateDecision(int $matchedId, float $confidence = 0.98, string $reason = 'same_commercial_need', bool $isDuplicate = true): void
{
    duplicateProvider()->queue(new DuplicateDetectionResult(
        isDuplicate: $isDuplicate,
        matchedRequestId: $matchedId,
        confidence: $confidence,
        reasonCode: $reason,
    ));
}

function classifyRequest(User $user, string $text = 'ABS Sensor', bool $withImage = false)
{
    $payload = ['request_text' => $text, 'submission_token' => (string) Str::ulid()];
    if ($withImage) {
        $payload['image'] = UploadedFile::fake()->image('part.jpg');
    }

    return test()->actingAs($user)->post(route('customer.requests.classify'), $payload);
}

function confirmRequest(User $user, RequestClassification $classification)
{
    return test()->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ]);
}

test('default daily request limit is 3 when no platform setting is stored', function () {
    expect(app(PlatformSettingService::class)->dailyCustomerRequestLimit())->toBe(3)
        ->and((int) config('customer_requests.default_daily_limit'))->toBe(3);

    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    $snapshot = app(CustomerRequestLimitService::class)->snapshot($customer);
    expect($snapshot['daily_limit'])->toBe(3)
        ->and($snapshot['global_limit'])->toBe(3);
});

test('existing explicit platform setting is not overwritten by the new default', function () {
    PlatformSetting::query()->create([
        'key' => PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT,
        'value' => '5',
    ]);
    app(PlatformSettingService::class)->forget(PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT);

    expect(app(PlatformSettingService::class)->dailyCustomerRequestLimit())->toBe(5);
});

test('per-customer daily override still applies', function () {
    ['customer' => $customer] = duplicateCustomer();
    $customer->update(['daily_request_limit_override' => 10]);

    expect(app(CustomerRequestLimitService::class)->effectiveLimit($customer->fresh()))->toBe(10);
});

test('duplicate checker reads max 6 previous requests from the same customer only', function () {
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(20);
    // Every classify() call here stays PendingClassification/ReadyForReview
    // without ever being confirmed, so it would otherwise trip the
    // "too many open AI attempts" anti-abuse ceiling well before the 8
    // sequential submissions this test needs.
    config(['customer_requests.max_open_ai_attempts' => 20]);
    ['user' => $userA, 'customer' => $customerA] = duplicateCustomer(['email' => 'dup-a@example.com']);
    ['user' => $userB] = duplicateCustomer(['email' => 'dup-b@example.com']);

    $adminRequest = CustomerRequest::factory()->create([
        'customer_id' => $customerA->id,
        'source' => Source::Admin,
        'status' => RequestStatus::Ready,
        'request_text' => 'ADMIN_RAW_SHOULD_NOT_APPEAR',
        'normalized_request_json' => ['item' => 'admin item', 'summary' => 'admin item'],
    ]);

    classifyRequest($userB, 'ABS Sensor other customer')->assertRedirect();

    for ($i = 1; $i <= 7; $i++) {
        classifyRequest($userA, 'ABS Sensor previous '.$i)->assertRedirect();
    }

    classifyRequest($userA, 'ABS Sensor eighth UNIQUE_RAW_PHRASE')->assertRedirect();

    $payload = duplicateProvider()->lastInput?->toAiPayload();
    expect($payload)->not->toBeNull()
        ->and($payload['previous_requests'])->toHaveCount(6);

    $ids = collect($payload['previous_requests'])->pluck('id')->all();
    $ownNewest = CustomerRequest::query()
        ->where('customer_id', $customerA->id)
        ->where('source', Source::Web)
        ->orderByDesc('id')
        ->skip(1)
        ->take(6)
        ->pluck('id')
        ->all();

    expect($ids)->toBe($ownNewest)
        ->and($ids)->not->toContain($adminRequest->id);

    $otherId = CustomerRequest::query()->where('customer_id', '!=', $customerA->id)->value('id');
    expect($ids)->not->toContain($otherId);
});

test('duplicate AI payload contains normalized snapshots and omits raw text and images', function () {
    ['user' => $user] = duplicateCustomer();

    classifyRequest($user, 'SECRET_RAW_WORDING ABS Sensor first', withImage: true)->assertRedirect();
    $first = CustomerRequest::query()->latest('id')->first();
    expect($first->normalized_request_json['item'] ?? null)->not->toBeEmpty();

    classifyRequest($user, 'SECRET_RAW_WORDING ABS Sensor second', withImage: true)->assertRedirect();

    $encoded = json_encode(duplicateProvider()->lastInput?->toAiPayload());
    expect($encoded)->not->toContain('SECRET_RAW_WORDING')
        ->and($encoded)->not->toContain('request_text')
        ->and($encoded)->not->toContain('image_url')
        ->and($encoded)->not->toContain('image_path')
        ->and($encoded)->not->toContain('part.jpg')
        ->and($encoded)->not->toContain($first->request_text)
        ->and($encoded)->toContain('"item"')
        ->and($encoded)->toContain((string) $first->id);
});

test('wording-only arabic english and synonym changes are blocked as the same commercial need', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    Notification::fake();

    classifyRequest($user, 'تيل فرامل أمامي شيفروليه جروف 2023')->assertRedirect();
    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    queueDuplicateDecision((int) $first->id);
    $response = classifyRequest($user, 'فحمات قدام لجروف موديل 23');
    $blockedOne = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $response->assertRedirect(route('customer.requests.show', $blockedOne));

    expect($blockedOne->ai_stage)->toBe(AiStage::DuplicateBlocked)
        ->and($blockedOne->status)->toBe(RequestStatus::Cancelled)
        ->and($blockedOne->duplicate_of_customer_request_id)->toBe($first->id);

    $this->actingAs($user)
        ->get(route('customer.requests.show', $blockedOne))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.message', CustomerRequestMessages::duplicateRequest())
            ->where('status.duplicate_of_request_public_id', $first->public_id)
        );

    queueDuplicateDecision((int) $first->id);
    classifyRequest($user, 'front brake pads Chevrolet Groove 2023')->assertRedirect();

    queueDuplicateDecision((int) $first->id);
    classifyRequest($user, 'chevrolet groove 2023 front brake pads synonym')->assertRedirect();

    // Only the single original request is ever live; every subsequent
    // duplicate attempt is kept but permanently cancelled.
    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('status', '!=', RequestStatus::Cancelled)->count())->toBe(1)
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->where('ai_stage', AiStage::DuplicateBlocked)->count())->toBe(3);

    $source = file_get_contents(app_path('Services/CustomerRequestDuplicateDetectionService.php'));
    expect($source)->toContain("Log::info('customer_request.duplicate_blocked'");
});

test('materially different specification product or position is allowed', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    classifyRequest($user, 'front brake pads Chevrolet Groove 2023')->assertRedirect();
    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    queueDuplicateDecision((int) $first->id, 0.99, 'different_specification', false);
    classifyRequest($user, 'rear brake pads Chevrolet Groove 2023')->assertRedirect();

    queueDuplicateDecision((int) $first->id, 0.99, 'different_item', false);
    classifyRequest($user, 'battery 70Ah')->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(3)
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->where('ai_stage', AiStage::DuplicateBlocked)->count())->toBe(0);
});

test('duplicate with confidence at or above 0.90 blocks and below threshold is allowed', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    classifyRequest($user)->assertRedirect();
    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    queueDuplicateDecision((int) $first->id, 0.90);
    classifyRequest($user, 'ABS Sensor wording change')->assertRedirect();
    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('status', '!=', RequestStatus::Cancelled)->count())->toBe(1);

    queueDuplicateDecision((int) $first->id, 0.89);
    classifyRequest($user, 'ABS Sensor allowed by threshold')->assertRedirect();
    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('status', '!=', RequestStatus::Cancelled)->count())->toBe(2);
});

test('duplicate block consumes no quota or extra credit and creates no matches or notifications', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    $category = Category::query()->first();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Notification::fake();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(3);
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        2,
        TransactionSource::PromotionalBonus,
        null,
        'promo',
        $this->admin,
    );

    classifyRequest($user)->assertRedirect();
    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $classification = RequestClassification::query()->where('customer_request_id', $first->id)->first();

    confirmRequest($user, $classification)->assertRedirect();

    $matches = RequestMatch::query()->count();
    $history = MerchantRequestMatch::query()->count();
    Notification::fake();
    $quota = app(CustomerRequestLimitService::class)->snapshot($customer->fresh());
    $extra = app(CustomerExtraRequestService::class)->balance((int) $customer->id);

    queueDuplicateDecision((int) $first->id);
    $response = classifyRequest($user, 'ABS Sensor again');
    $blocked = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $response->assertRedirect(route('customer.requests.show', $blocked));

    expect($blocked->ai_stage)->toBe(AiStage::DuplicateBlocked)
        ->and($blocked->duplicate_of_customer_request_id)->toBe($first->id)
        ->and($blocked->quota_consumed_at)->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->snapshot($customer->fresh())['used'])->toBe($quota['used'])
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe($extra)
        ->and(RequestMatch::query()->count())->toBe($matches)
        ->and(MerchantRequestMatch::query()->count())->toBe($history);

    Notification::assertNothingSent();
});

test('confirm-time duplicate discards the pending request and restores extra credit', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->admin,
    );

    classifyRequest($user, 'ABS Sensor one')->assertRedirect();
    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $firstClassification = RequestClassification::query()->where('customer_request_id', $first->id)->first();
    confirmRequest($user, $firstClassification)->assertRedirect();

    // The one daily free slot is now spent by $first; the extra credit
    // hasn't been touched at all yet.
    expect(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(1);

    classifyRequest($user, 'ABS Sensor two')->assertRedirect();
    // Classification alone never consumes quota/credit, no matter how many
    // rows now exist.
    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2)
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(1);

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->where('status', RequestStatus::PendingClassification)->first();
    $pendingClassification = RequestClassification::query()->where('customer_request_id', $pending->id)->first();

    // The early check (right after classification) missed it; the final
    // check, run against the confirmed category at finalize time, catches
    // it instead — discarding the pending row without ever touching the
    // extra-credit balance, since finalization never got that far.
    queueDuplicateDecision((int) $first->id);
    confirmRequest($user, $pendingClassification)->assertRedirect(route('customer.requests.show', $pending));

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2)
        ->and($pending->fresh()->ai_stage)->toBe(AiStage::DuplicateBlocked)
        ->and($pending->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($pending->fresh()->quota_consumed_at)->toBeNull()
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(1)
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);
});

test('invalid json and provider failure fail open', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    classifyRequest($user)->assertRedirect();

    duplicateProvider()->handler = fn () => throw new DuplicateDetectionFailedException('invalid json');
    classifyRequest($user, 'ABS Sensor fail open json')->assertRedirect();

    duplicateProvider()->handler = fn () => throw new DuplicateDetectionFailedException('The duplicate detection provider timed out.');
    classifyRequest($user, 'ABS Sensor fail open timeout')->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(3)
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->where('ai_stage', AiStage::DuplicateBlocked)->count())->toBe(0);
});

test('non-comparable snapshots skip the AI duplicate check', function () {
    ['customer' => $customer] = duplicateCustomer();
    CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::Web,
        'status' => RequestStatus::Ready,
        'normalized_request_json' => ['item' => 'ABS Sensor', 'summary' => 'abs sensor'],
    ]);

    duplicateProvider()->reset();
    app(CustomerRequestDuplicateDetectionService::class)->assertNotDuplicate($customer, [
        'item' => null,
        'summary' => null,
        'category_public_id' => null,
    ]);

    expect(duplicateProvider()->calls)->toBe(0);
});

test('concurrent duplicate submissions are serialized with a customer lock', function () {
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();
    classifyRequest($user)->assertRedirect();
    $first = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    queueDuplicateDecision((int) $first->id);
    $response = classifyRequest($user, 'ABS Sensor concurrent');
    $blocked = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $response->assertRedirect(route('customer.requests.show', $blocked));

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('status', '!=', RequestStatus::Cancelled)->count())->toBe(1);

    $source = file_get_contents(app_path('Services/CustomerRequestDuplicateDetectionService.php'));
    $pending = file_get_contents(app_path('Services/CustomerRequestService.php'));
    expect($source)->toContain('Cache::lock')
        ->and($source)->toContain('customer-request-create:')
        ->and($pending)->toContain('lockForUpdate');
});

test('admin-created requests stay unchanged and are excluded from duplicate comparison', function () {
    $category = Category::query()->first();
    ['user' => $user, 'customer' => $customer] = duplicateCustomer();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'category_id' => $category->public_id,
            'request_text' => 'Admin created ABS Sensor',
            'status' => RequestStatus::Ready->value,
        ])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('source', Source::Admin)->count())->toBe(1);

    classifyRequest($user, 'ABS Sensor')->assertRedirect();
    expect(duplicateProvider()->calls)->toBe(0)
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->where('source', Source::Web)->count())->toBe(1);
});

test('contact reveal limit remains 3', function () {
    expect((int) config('customer_requests.contact_reveal_limit'))->toBe(3);
});

test('duplicate confidence threshold default is 0.90', function () {
    expect(app(CustomerRequestDuplicateDetectionService::class)->confidenceThreshold())->toBe(0.90);
});
