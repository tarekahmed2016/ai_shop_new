<?php

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Jobs\ClassifyCustomerRequestJob;
use App\Jobs\FinalizeCustomerRequestJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\User;
use App\Services\CustomerRequestLimitService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * classification.async_enabled defaults to false: the live HTTP path must
 * stay the pre-pipeline synchronous classify/confirm bodies until the
 * flag is flipped. These tests never call enableAsyncClassification().
 */
beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    config(['classification.async_enabled' => false]);
});

function legacyClassificationCustomer(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ]);

    return compact('user', 'customer');
}

test('legacy classify stays on the create page and does not dispatch pipeline jobs', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active, 'name_en' => 'Auto Spare Parts']);
    ['user' => $user, 'customer' => $customer] = legacyClassificationCustomer();

    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestCreatePage', false)
            ->where('classification.detected_item', 'ABS Sensor')
            ->where('classification.primary.category_public_id', $category->public_id)
            ->where('classification.failed', false)
        );

    Queue::assertNothingPushed();
    Queue::assertNotPushed(ClassifyCustomerRequestJob::class);

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($pending)->not->toBeNull()
        ->and($pending->status)->toBe(RequestStatus::PendingClassification)
        ->and($pending->ai_stage)->toBeNull()
        ->and($pending->quota_consumed_at)->not->toBeNull()
        ->and(app(CustomerRequestLimitService::class)->todayCount($customer->fresh()))->toBe(1);
});

test('legacy confirm finalizes inline without the finalize job', function () {
    Category::factory()->create(['status' => CategoryStatus::Active, 'name_en' => 'Auto Spare Parts']);
    ['user' => $user, 'customer' => $customer] = legacyClassificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
        ])
        ->assertOk();

    $classification = RequestClassification::query()->latest('id')->first();

    Queue::fake();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
        ])
        ->assertRedirect();

    Queue::assertNotPushed(FinalizeCustomerRequestJob::class);

    $request = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($request->status)->toBe(RequestStatus::Ready)
        ->and($request->ai_stage)->toBeNull()
        ->and($request->quota_consumed_at)->not->toBeNull();
});

test('legacy path still requires no submission_token', function () {
    Category::factory()->create(['status' => CategoryStatus::Active]);
    ['user' => $user] = legacyClassificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
        ])
        ->assertOk()
        ->assertSessionDoesntHaveErrors('submission_token');
});
