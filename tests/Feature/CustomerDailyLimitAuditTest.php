<?php

use App\Enums\CustomerDailyRequestLimitChanges\ChangeType;
use App\Enums\Customers\Status as CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerDailyRequestLimitChange;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingChange;
use App\Models\User;
use App\Services\CustomerDailyRequestLimitAuditService;
use App\Services\CustomerService;
use App\Services\PlatformSettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

function limitAuditAdmin(): User
{
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function limitAuditCustomer(array $customerAttrs = []): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(array_merge([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
        'daily_request_limit_override' => null,
    ], $customerAttrs));

    return compact('user', 'customer');
}

function customerLimitPayload(Customer $customer, mixed $override, ?string $notes = null): array
{
    $payload = [
        'name' => $customer->name,
        'phone' => $customer->phone,
        'email' => $customer->email,
        'status' => CustomerStatus::Active->value,
        'daily_request_limit_override' => $override,
    ];

    if ($notes !== null) {
        $payload['daily_request_limit_notes'] = $notes;
    }

    return $payload;
}

test('customer override history records set update and clear with effective limits notes and admin identity', function () {
    $admin = limitAuditAdmin();
    ['user' => $user, 'customer' => $customer] = limitAuditCustomer();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(5);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, 10, 'VIP customer'))
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, 20, 'Temporary increase'))
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, 20, 'same value'))
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, '', 'Manual support adjustment'))
        ->assertRedirect();

    $rows = CustomerDailyRequestLimitChange::query()
        ->where('customer_id', $customer->id)
        ->orderBy('id')
        ->get();

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->old_override)->toBeNull()
        ->and((int) $rows[0]->new_override)->toBe(10)
        ->and($rows[0]->old_effective_limit)->toBe(5)
        ->and($rows[0]->new_effective_limit)->toBe(10)
        ->and($rows[0]->change_type)->toBe(ChangeType::SetOverride)
        ->and($rows[0]->notes)->toBe('VIP customer')
        ->and($rows[0]->changed_by_user_id)->toBe($admin->id)
        ->and((int) $rows[1]->old_override)->toBe(10)
        ->and((int) $rows[1]->new_override)->toBe(20)
        ->and($rows[1]->old_effective_limit)->toBe(10)
        ->and($rows[1]->new_effective_limit)->toBe(20)
        ->and($rows[1]->change_type)->toBe(ChangeType::UpdateOverride)
        ->and($rows[1]->notes)->toBe('Temporary increase')
        ->and((int) $rows[2]->old_override)->toBe(20)
        ->and($rows[2]->new_override)->toBeNull()
        ->and($rows[2]->old_effective_limit)->toBe(20)
        ->and($rows[2]->new_effective_limit)->toBe(5)
        ->and($rows[2]->change_type)->toBe(ChangeType::ClearOverride)
        ->and($rows[2]->notes)->toBe('Manual support adjustment');

    $this->actingAs($admin)
        ->get(route('customers.daily-limit-history', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/CustomerDailyLimitHistoryPage', false)
            ->has('changes.data', 3)
            ->where('changes.data.0.change_type', ChangeType::ClearOverride->value)
            ->where('changes.data.0.new_override', null)
            ->where('changes.data.0.new_effective_limit', 5)
            ->where('changes.data.0.changed_by.name', $admin->name)
            ->where('changes.data.2.old_override', null)
            ->where('changes.data.2.new_override', 10)
            ->where('changes.data.2.notes', 'VIP customer')
        );

    expect($customer->fresh()->daily_request_limit_override)->toBeNull()
        ->and(CustomerDailyRequestLimitChange::query()->where('customer_id', $customer->id)->count())->toBe(3);
});

test('creating a customer with an override writes set_override history', function () {
    $admin = limitAuditAdmin();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(5);

    $this->actingAs($admin)
        ->post(route('customers.store'), [
            'name' => 'Override Customer',
            'phone' => '01000000001',
            'email' => 'override-history@example.com',
            'status' => CustomerStatus::Active->value,
            'daily_request_limit_override' => 10,
            'daily_request_limit_notes' => 'VIP customer',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('email', 'override-history@example.com')->first();
    $row = CustomerDailyRequestLimitChange::query()->where('customer_id', $customer->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->old_override)->toBeNull()
        ->and((int) $row->new_override)->toBe(10)
        ->and($row->change_type)->toBe(ChangeType::SetOverride)
        ->and($row->notes)->toBe('VIP customer')
        ->and($row->changed_by_user_id)->toBe($admin->id);
});

test('failed override audit rolls back the customer update', function () {
    $admin = limitAuditAdmin();
    ['customer' => $customer] = limitAuditCustomer();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(5);

    $this->mock(CustomerDailyRequestLimitAuditService::class, function ($mock) {
        $mock->shouldReceive('recordOverrideChange')->once()->andThrow(new RuntimeException('audit failed'));
    });
    app()->forgetInstance(CustomerService::class);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, 10, 'VIP customer'))
        ->assertStatus(500);

    expect($customer->fresh()->daily_request_limit_override)->toBeNull()
        ->and(CustomerDailyRequestLimitChange::query()->count())->toBe(0);
});

test('customer cannot view or modify daily limit history', function () {
    $admin = limitAuditAdmin();
    ['user' => $user, 'customer' => $customer] = limitAuditCustomer();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(5);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, 10, 'VIP customer'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('customers.daily-limit-history', $customer))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->put(route('customers.update', $customer), customerLimitPayload($customer, 99))
        ->assertRedirect(route('login'));

    expect((int) $customer->fresh()->daily_request_limit_override)->toBe(10)
        ->and(CustomerDailyRequestLimitChange::query()->count())->toBe(1)
        ->and(Route::has('customers.daily-limit-history.destroy'))->toBeFalse()
        ->and(collect(Route::getRoutes())->contains(function ($route) {
            return in_array('DELETE', $route->methods(), true)
                && str_contains($route->uri(), 'daily-limit-history');
        }))->toBeFalse();
});

test('customer daily limit history paginates newest first without N+1', function () {
    $admin = limitAuditAdmin();
    ['customer' => $customer] = limitAuditCustomer();
    $actors = User::factory()->count(8)->create();

    foreach (range(1, 26) as $index) {
        CustomerDailyRequestLimitChange::factory()->create([
            'customer_id' => $customer->id,
            'old_override' => $index === 1 ? null : $index,
            'new_override' => $index + 1,
            'effective_global_limit' => 5,
            'old_effective_limit' => $index === 1 ? 5 : $index,
            'new_effective_limit' => $index + 1,
            'change_type' => $index === 1 ? ChangeType::SetOverride : ChangeType::UpdateOverride,
            'changed_by_user_id' => $actors[($index - 1) % $actors->count()]->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)
        ->get(route('customers.daily-limit-history', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('changes.data', 25)
            ->where('changes.per_page', 25)
            ->where('changes.total', 26)
            ->where('changes.data.0.new_override', 27)
            ->where('changes.data.24.new_override', 3)
        );

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $userByIdQueries = $queries->filter(function (array $query) {
        return preg_match('/from ["`]?users["`]? where ["`]?id["`]? = \?/i', $query['query']) === 1;
    });

    expect($userByIdQueries->count())->toBeLessThan(3);

    $this->actingAs($admin)
        ->get(route('customers.daily-limit-history', $customer).'?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('changes.data', 1)
            ->where('changes.data.0.new_override', 2)
        );
});

test('global daily limit history records one row and does not fan out to customers', function () {
    $admin = limitAuditAdmin();
    limitAuditCustomer();
    limitAuditCustomer(['email' => 'second-limit@example.com', 'phone' => '01000000002']);
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(5);

    $this->actingAs($admin)
        ->put(route('customers.settings.daily-request-limit'), ['daily_limit' => 10])
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('customers.settings.daily-request-limit'), ['daily_limit' => 10])
        ->assertRedirect();

    expect(PlatformSettingChange::query()->where('key', PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT)->count())->toBe(1)
        ->and(CustomerDailyRequestLimitChange::query()->count())->toBe(0);

    $row = PlatformSettingChange::query()->first();
    expect($row->old_value)->toBe('5')
        ->and($row->new_value)->toBe('10')
        ->and($row->changed_by_user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/CustomersPage', false)
            ->where('dailyCustomerRequestLimit', 10)
            ->has('globalLimitHistory.data', 1)
            ->where('globalLimitHistory.data.0.old_value', '5')
            ->where('globalLimitHistory.data.0.new_value', '10')
            ->where('globalLimitHistory.data.0.changed_by.name', $admin->name)
        );
});

test('failed global limit audit rolls back the setting update', function () {
    $admin = limitAuditAdmin();
    app(PlatformSettingService::class)->setDailyCustomerRequestLimit(5);

    $this->mock(CustomerDailyRequestLimitAuditService::class, function ($mock) {
        $mock->shouldReceive('recordGlobalLimitChange')->once()->andThrow(new RuntimeException('audit failed'));
    });
    app()->forgetInstance(CustomerService::class);

    $this->actingAs($admin)
        ->put(route('customers.settings.daily-request-limit'), ['daily_limit' => 10])
        ->assertStatus(500);

    expect(app(PlatformSettingService::class)->dailyCustomerRequestLimit())->toBe(5)
        ->and(PlatformSettingChange::query()->count())->toBe(0);
});
