<?php

use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Models\User;
use App\Services\PlatformSettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(3);
    enableAsyncClassification();
});

function limitCustomer(array $userAttrs = [], array $customerAttrs = []): array
{
    $user = User::factory()->create($userAttrs);
    $customer = Customer::factory()->create(array_merge([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ], $customerAttrs));

    return compact('user', 'customer');
}

/**
 * Only creates the row and runs it through classification (no quota/credit
 * consumption — see CustomerRequestLimitService::todayCount()). Callers
 * that need a slot actually consumed must additionally confirm via
 * classifyAndConfirm().
 */
function classifyOnce(User $user, string $text = 'ABS Sensor'): RequestClassification
{
    test()->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => $text,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    return RequestClassification::query()->latest('id')->first();
}

/**
 * Drives the async pipeline through to its single quota-consuming step
 * (FinalizeCustomerRequestJob, run inline here because phpunit.xml sets
 * QUEUE_CONNECTION=sync) so the daily slot is actually spent.
 */
function classifyAndConfirm(User $user, string $text = 'ABS Sensor'): CustomerRequest
{
    $classification = classifyOnce($user, $text);

    test()->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    return $classification->customerRequest->fresh();
}

test('global daily limit applies to new customer requests', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    classifyAndConfirm($user, 'ABS Sensor one');
    classifyAndConfirm($user, 'ABS Sensor two');
    classifyAndConfirm($user, 'ABS Sensor three');

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor extra',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(3);
});

test('per customer override applies and clearing it returns to global', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = limitCustomer();
    $customer->update(['daily_request_limit_override' => 1]);

    classifyAndConfirm($user);
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor two', 'submission_token' => (string) Str::ulid()])
        ->assertSessionHasErrors('request_text');

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'status' => CustomerStatus::Active->value,
            'daily_request_limit_override' => '',
        ])
        ->assertRedirect();

    expect($customer->fresh()->daily_request_limit_override)->toBeNull();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor after clear', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2);
});

test('below the limit succeeds and at the limit is rejected', function () {
    Category::factory()->create();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    classifyAndConfirm($user, 'ABS Sensor');

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor again', 'submission_token' => (string) Str::ulid()])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('a different customer is unaffected by another customers usage', function () {
    Category::factory()->create();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    ['user' => $userA] = limitCustomer(['email' => 'limit-a@example.com']);
    ['user' => $userB, 'customer' => $customerB] = limitCustomer(['email' => 'limit-b@example.com']);

    classifyAndConfirm($userA);
    $this->actingAs($userB)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customerB->id)->count())->toBe(1);
});

test('usage resets on the next Oman local day', function () {
    Category::factory()->create();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    $this->travelTo(Carbon::parse('2026-08-25 20:00:00', 'Asia/Muscat'));
    classifyAndConfirm($user);
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor same day', 'submission_token' => (string) Str::ulid()])
        ->assertSessionHasErrors('request_text');

    $this->travelTo(Carbon::parse('2026-08-26 00:05:00', 'Asia/Muscat'));
    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor next day', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2);
});

test('edit and confirmation do not consume additional daily slots', function () {
    $category = Category::factory()->create(['name_en' => 'Auto Spare Parts']);
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    $classification = classifyOnce($user);
    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $classification->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1);

    $request = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $customer->public_id,
            'category_id' => $category->public_id,
            'request_text' => 'Edited ABS Sensor',
            'status' => RequestStatus::Ready->value,
        ])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('failed validation does not consume a daily slot', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(0);
});

test('blocked contact attempts do not consume a successful daily slot', function () {
    Category::factory()->create();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Call me on 9xxxxxxx',
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Suspended);

    $this->actingAs($this->admin)
        ->post(route('customers.reactivate', $customer))
        ->assertRedirect();

    $this->actingAs($user->fresh())
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('concurrent last-slot creates cannot exceed the daily limit', function () {
    Category::factory()->create();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    // Neither classify() call consumes quota (it's not consumed until
    // confirm/finalize), so both are free to create their row — exactly
    // like two browser tabs/requests racing before either has confirmed.
    $first = classifyOnce($user, 'ABS Sensor one');
    $second = classifyOnce($user, 'ABS Sensor two');

    test()->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $first), [
            'category_id' => $first->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    // The second confirm/finalize is where the race is actually resolved,
    // atomically, under a row lock on the customer — it must lose.
    test()->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $second), [
            'category_id' => $second->suggestedCategory->public_id,
            'submission_token' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    expect($first->customerRequest->fresh()->ai_stage)->toBe(AiStage::Ready)
        ->and($first->customerRequest->fresh()->quota_consumed_at)->not->toBeNull()
        ->and($second->customerRequest->fresh()->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($second->customerRequest->fresh()->ai_stage_reason)->toBe('quota_exhausted_at_finalization')
        ->and($second->customerRequest->fresh()->quota_consumed_at)->toBeNull()
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(2);
});

test('customer cannot modify the daily limit override', function () {
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'status' => CustomerStatus::Active->value,
            'daily_request_limit_override' => 99,
        ])
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => (string) Str::ulid(),
            'daily_request_limit_override' => 99,
        ])
        ->assertSessionHasErrors('daily_request_limit_override');

    expect($customer->fresh()->daily_request_limit_override)->toBeNull();
});

test('admin can set and clear override and sees usage on the customers index', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = limitCustomer(['email' => 'usage@example.com']);

    $this->actingAs($this->admin)
        ->put(route('customers.settings.daily-request-limit'), ['daily_limit' => 5])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'status' => CustomerStatus::Active->value,
            'daily_request_limit_override' => 10,
        ])
        ->assertRedirect();

    expect((int) $customer->fresh()->daily_request_limit_override)->toBe(10);

    classifyAndConfirm($user);

    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/CustomersPage', false)
            ->where('dailyCustomerRequestLimit', 5)
            ->where('dailyCustomerRequestTimezone', 'Asia/Muscat')
            ->where('customers.data.0.requests_today', 1)
            ->where('customers.data.0.daily_limit', 10)
            ->where('customers.data.0.remaining_today', 9)
            ->where('customers.data.0.daily_limit_override', 10)
        );

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'status' => CustomerStatus::Active->value,
            'daily_request_limit_override' => '',
        ])
        ->assertRedirect();

    expect($customer->fresh()->daily_request_limit_override)->toBeNull();
});

test('admin created requests do not consume the customer daily quota', function () {
    $category = Category::factory()->create();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(1);
    ['user' => $user, 'customer' => $customer] = limitCustomer();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'category_id' => $category->public_id,
            'request_text' => 'Admin created',
            'status' => RequestStatus::Ready->value,
        ])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('source', Source::Admin)->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => (string) Str::ulid()])
        ->assertRedirect();
});

test('customer home and create pages show remaining quota', function () {
    Category::factory()->create();
    ['user' => $user] = limitCustomer();
    classifyAndConfirm($user);

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('requestQuota.used', 1)
            ->where('requestQuota.daily_limit', 3)
            ->where('requestQuota.remaining', 2)
            ->where('requestQuota.timezone', 'Asia/Muscat')
        );

    $this->actingAs($user)
        ->get(route('customer.requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('requestQuota.remaining', 2)
            ->missing('availableCategories')
        );
});
