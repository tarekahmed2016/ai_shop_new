<?php

use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\User;
use App\Services\CustomerRequestLimitService;
use App\Support\CustomerRequests\QuotaConsumedAtBackfill;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('quota_consumed_at backfill preserves todayCount parity with the pre-pipeline row count', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);
    $other = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $webReady = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::Web,
        'status' => RequestStatus::Ready,
        'quota_consumed_at' => now(),
    ]);
    $whatsappReady = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::WhatsApp,
        'status' => RequestStatus::Ready,
        'quota_consumed_at' => now(),
    ]);
    $failedClassification = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::Web,
        'status' => RequestStatus::PendingClassification,
        'quota_consumed_at' => now(),
    ]);
    RequestClassification::factory()->create([
        'customer_request_id' => $failedClassification->id,
        'status' => ClassificationStatus::Failed,
        'reason' => 'provider-failed',
    ]);
    $adminReady = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::Admin,
        'status' => RequestStatus::Ready,
        'quota_consumed_at' => now(),
    ]);
    CustomerRequest::factory()->create([
        'customer_id' => $other->id,
        'source' => Source::Web,
        'status' => RequestStatus::Ready,
        'quota_consumed_at' => now(),
    ]);

    CustomerRequest::query()->update(['quota_consumed_at' => null]);

    $limits = app(CustomerRequestLimitService::class);
    [$start, $end] = $limits->todayUtcRange();

    $legacyRawCount = CustomerRequest::query()
        ->where('customer_id', $customer->id)
        ->whereIn('source', [Source::Web, Source::WhatsApp])
        ->whereBetween('created_at', [$start, $end])
        ->count();

    expect($limits->todayCount($customer->fresh()))->toBe(0)
        ->and($legacyRawCount)->toBe(3);

    expect(QuotaConsumedAtBackfill::run())->toBeGreaterThan(0);

    expect($limits->todayCount($customer->fresh()))->toBe($legacyRawCount)
        ->and($webReady->fresh()->quota_consumed_at?->eq($webReady->created_at))->toBeTrue()
        ->and($whatsappReady->fresh()->quota_consumed_at?->eq($whatsappReady->created_at))->toBeTrue()
        ->and($failedClassification->fresh()->quota_consumed_at?->eq($failedClassification->created_at))->toBeTrue()
        ->and($adminReady->fresh()->quota_consumed_at)->toBeNull();

    CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'source' => Source::Web,
        'status' => RequestStatus::PendingClassification,
        'quota_consumed_at' => null,
    ]);

    expect($limits->todayCount($customer->fresh()))->toBe($legacyRawCount);
});
