<?php

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerExtraRequests\TransactionType;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\Customers\Status as CustomerStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use App\Services\PlatformSettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->extraAdmin = User::factory()->create();
    $this->extraAdmin->assignRole('admin');
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    Category::factory()->create();
    enableAsyncClassification();
});

function extraRequestCustomer(array $userAttrs = []): array
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

function extraClassify(User $user, string $text = 'ABS Sensor')
{
    return test()->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => $text,
            'submission_token' => (string) Str::ulid(),
        ]);
}

/**
 * Drives the async pipeline all the way through its single
 * quota/credit-consuming step: classify (no consumption) then confirm the
 * suggested category (the only place that can consume — inside
 * FinalizeCustomerRequestJob, run inline here because phpunit.xml sets
 * QUEUE_CONNECTION=sync).
 */
function extraClassifyAndConfirm(User $user, string $text = 'ABS Sensor'): CustomerRequest
{
    extraClassify($user, $text)->assertRedirect();

    $classification = RequestClassification::query()->latest('id')->first();

    test()->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    return $classification->customerRequest->fresh();
}

test('daily quota available uses a free slot and leaves extra balance unchanged', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        3,
        TransactionSource::PromotionalBonus,
        null,
        'promo',
        $this->extraAdmin,
    );

    $request = extraClassifyAndConfirm($user);

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($request->quota_consumed_at)->not->toBeNull()
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(3)
        ->and(CustomerExtraRequestTransaction::query()->where('type', TransactionType::RequestCreate)->count())->toBe(0);
});

test('exhausted daily quota consumes one extra credit then rejects the next request', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->extraAdmin,
    );

    extraClassifyAndConfirm($user, 'ABS Sensor one'); // uses the free daily slot
    extraClassifyAndConfirm($user, 'ABS Sensor two'); // daily slot exhausted -> consumes the extra credit

    extraClassify($user, 'ABS Sensor three')->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2)
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(0)
        ->and(CustomerExtraRequestTransaction::query()->where('type', TransactionType::RequestCreate)->count())->toBe(1);
});

test('next oman day uses free quota before extra credits and extra balance persists', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        2,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->extraAdmin,
    );

    $this->travelTo(Carbon::parse('2026-08-25 20:00:00', 'Asia/Muscat'));
    extraClassifyAndConfirm($user, 'ABS Sensor day one free');
    extraClassifyAndConfirm($user, 'ABS Sensor day one extra');
    expect(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(1);

    $this->travelTo(Carbon::parse('2026-08-26 00:05:00', 'Asia/Muscat'));
    extraClassifyAndConfirm($user, 'ABS Sensor next day free');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(3)
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(1)
        ->and(CustomerExtraRequestTransaction::query()->where('type', TransactionType::RequestCreate)->count())->toBe(1);
});

test('failed validation and contact-blocked requests do not deduct extra credits', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    extraClassify($user)->assertRedirect();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        2,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->extraAdmin,
    );

    extraClassify($user, '')->assertSessionHasErrors('request_text');
    extraClassify($user, 'Call me on 9xxxxxxx')->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(2)
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Suspended);
});

test('confirmation and edit do not deduct extra credits', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    extraClassify($user)->assertRedirect();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        2,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->extraAdmin,
    );

    $classification = RequestClassification::query()->latest('id')->first();
    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $this->actingAs($this->extraAdmin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $customer->public_id,
            'category_id' => $classification->suggestedCategory->public_id,
            'request_text' => 'Edited ABS Sensor',
            'status' => $request->status->value,
        ])
        ->assertRedirect();

    expect(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(2)
        ->and(CustomerExtraRequestTransaction::query()->where('type', TransactionType::RequestCreate)->count())->toBe(0);
});

test('the same customer request cannot deduct extra credit twice', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    extraClassifyAndConfirm($user); // uses the free daily slot
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        3,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->extraAdmin,
    );
    $request = extraClassifyAndConfirm($user, 'ABS Sensor extra'); // exhausted -> consumes 1 extra credit

    $consumed = app(CustomerExtraRequestService::class)->consumeForNewRequest($customer, $request);

    expect($consumed)->toBeFalse()
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(2)
        ->and(CustomerExtraRequestTransaction::query()->where('customer_request_id', $request->id)->count())->toBe(1);
});

test('extra credit deduction rolls back when ledger insert fails', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();
    extraClassifyAndConfirm($user); // uses the free daily slot
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        TransactionSource::PromotionalBonus,
        null,
        null,
        $this->extraAdmin,
    );

    $fail = true;
    CustomerExtraRequestTransaction::creating(function ($transaction) use (&$fail) {
        if ($fail && $transaction->type === TransactionType::RequestCreate) {
            throw ValidationException::withMessages([
                'request_text' => 'forced',
            ]);
        }
    });

    extraClassify($user, 'ABS Sensor extra')->assertRedirect();
    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->latest('id')->first();
    $classification = RequestClassification::query()->where('customer_request_id', $pending->id)->latest('id')->first();

    // The HTTP confirm call itself still succeeds (redirects) — the ledger
    // failure happens *inside* FinalizeCustomerRequestJob (run inline by
    // QUEUE_CONNECTION=sync) and is turned into ready_for_review with a
    // quota_exhausted_at_finalization reason, never a session error.
    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    $fail = false;

    expect($pending->fresh()->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($pending->fresh()->ai_stage_reason)->toBe('quota_exhausted_at_finalization')
        ->and($pending->fresh()->quota_consumed_at)->toBeNull()
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2)
        ->and(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(1);
});

test('customer cannot modify own extra request credits', function () {
    ['user' => $user, 'customer' => $customer] = extraRequestCustomer();

    $this->actingAs($user)
        ->post(route('customers.extra-requests.store', $customer), [
            'amount' => 5,
            'source' => TransactionSource::PromotionalBonus->value,
        ])
        ->assertRedirect(route('login'));

    expect(app(CustomerExtraRequestService::class)->balance((int) $customer->id))->toBe(0);
});
