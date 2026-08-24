<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role as MembershipRole;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MarketerService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function marketersIndexAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function attachMarketerReferral(Marketer $marketer, User $referred): MarketerReferral
{
    return MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $referred->id,
        'referral_code' => $marketer->referral_code,
        'landing_path' => '/',
        'registered_at' => now(),
    ]);
}

function attachActiveCustomer(User $user, CustomerStatus $status = CustomerStatus::Active): Customer
{
    return Customer::factory()->create([
        'user_id' => $user->id,
        'status' => $status,
    ]);
}

function attachMerchantMembership(
    User $user,
    Merchant $merchant,
    MembershipStatus $status = MembershipStatus::Active,
    MembershipRole $role = MembershipRole::Staff,
): MerchantUser {
    return MerchantUser::query()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

/**
 * @param  callable(): mixed  $callback
 * @return list<array{query: string, bindings: array<int, mixed>, time: float|null}>
 */
function captureMarketerIndexQueries(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return $log;
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function marketerIndexRow(array $rows, Marketer $marketer): array
{
    $row = collect($rows)->first(
        fn (array $row) => ($row['public_id'] ?? null) === $marketer->public_id
    );

    expect($row)->not->toBeNull();

    return $row;
}

test('admin marketers index counts capabilities with database aggregates', function () {
    $admin = marketersIndexAdmin();

    $zero = Marketer::factory()->create(['user_id' => User::factory()->create(['name' => 'Zero Referrals'])->id]);

    $customerOnlyMarketer = Marketer::factory()->create(['user_id' => User::factory()->create(['name' => 'Customer Only Marketer'])->id]);
    $customerOnly = User::factory()->create();
    attachActiveCustomer($customerOnly);
    attachMarketerReferral($customerOnlyMarketer, $customerOnly);

    $merchantOnlyMarketer = Marketer::factory()->create(['user_id' => User::factory()->create(['name' => 'Merchant Only Marketer'])->id]);
    $merchantOnly = User::factory()->create();
    attachMerchantMembership($merchantOnly, Merchant::factory()->create());
    attachMarketerReferral($merchantOnlyMarketer, $merchantOnly);

    $dualMarketer = Marketer::factory()->create(['user_id' => User::factory()->create(['name' => 'Dual Marketer'])->id]);
    $dual = User::factory()->create();
    attachActiveCustomer($dual);
    attachMerchantMembership($dual, Merchant::factory()->create(), role: MembershipRole::Owner);
    attachMarketerReferral($dualMarketer, $dual);

    $mixed = Marketer::factory()->create(['user_id' => User::factory()->create(['name' => 'Mixed Marketer'])->id]);

    $inactiveCustomer = User::factory()->create();
    attachActiveCustomer($inactiveCustomer, CustomerStatus::Inactive);
    attachMarketerReferral($mixed, $inactiveCustomer);

    $inactiveMembership = User::factory()->create();
    attachMerchantMembership(
        $inactiveMembership,
        Merchant::factory()->create(),
        MembershipStatus::Inactive,
        MembershipRole::Owner,
    );
    attachMarketerReferral($mixed, $inactiveMembership);

    $inactiveMerchantUser = User::factory()->create();
    attachMerchantMembership(
        $inactiveMerchantUser,
        Merchant::factory()->create(['status' => MerchantStatus::Inactive]),
        MembershipStatus::Active,
        MembershipRole::Owner,
    );
    attachMarketerReferral($mixed, $inactiveMerchantUser);

    $multiMembership = User::factory()->create();
    attachActiveCustomer($multiMembership);
    attachMerchantMembership($multiMembership, Merchant::factory()->create());
    attachMerchantMembership($multiMembership, Merchant::factory()->create());
    attachMarketerReferral($mixed, $multiMembership);

    User::factory()->count(20)->create()->each(function (User $user) use ($mixed) {
        attachActiveCustomer($user);
        attachMarketerReferral($mixed, $user);
    });

    $foreign = Marketer::factory()->create();
    $foreignReferred = User::factory()->create();
    attachActiveCustomer($foreignReferred);
    attachMerchantMembership($foreignReferred, Merchant::factory()->create());
    attachMarketerReferral($foreign, $foreignReferred);

    $response = $this->actingAs($admin)
        ->get(route('marketers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketers/MarketersPage', false)
            ->has('marketers.data', 6));

    $rows = $response->inertiaProps('marketers.data');

    $zeroRow = marketerIndexRow($rows, $zero);
    expect((int) $zeroRow['referrals_count'])->toBe(0)
        ->and((int) $zeroRow['customer_count'])->toBe(0)
        ->and((int) $zeroRow['merchant_count'])->toBe(0)
        ->and((int) $zeroRow['dual_count'])->toBe(0);

    $customerRow = marketerIndexRow($rows, $customerOnlyMarketer);
    expect((int) $customerRow['referrals_count'])->toBe(1)
        ->and((int) $customerRow['customer_count'])->toBe(1)
        ->and((int) $customerRow['merchant_count'])->toBe(0)
        ->and((int) $customerRow['dual_count'])->toBe(0);

    $merchantRow = marketerIndexRow($rows, $merchantOnlyMarketer);
    expect((int) $merchantRow['referrals_count'])->toBe(1)
        ->and((int) $merchantRow['customer_count'])->toBe(0)
        ->and((int) $merchantRow['merchant_count'])->toBe(1)
        ->and((int) $merchantRow['dual_count'])->toBe(0);

    $dualRow = marketerIndexRow($rows, $dualMarketer);
    expect((int) $dualRow['referrals_count'])->toBe(1)
        ->and((int) $dualRow['customer_count'])->toBe(1)
        ->and((int) $dualRow['merchant_count'])->toBe(1)
        ->and((int) $dualRow['dual_count'])->toBe(1);

    $mixedRow = marketerIndexRow($rows, $mixed);
    expect((int) $mixedRow['referrals_count'])->toBe(24)
        ->and((int) $mixedRow['customer_count'])->toBe(21)
        ->and((int) $mixedRow['merchant_count'])->toBe(1)
        ->and((int) $mixedRow['dual_count'])->toBe(1);

    $foreignRow = marketerIndexRow($rows, $foreign);
    expect((int) $foreignRow['referrals_count'])->toBe(1)
        ->and((int) $foreignRow['customer_count'])->toBe(1)
        ->and((int) $foreignRow['merchant_count'])->toBe(1)
        ->and((int) $foreignRow['dual_count'])->toBe(1);
});

test('admin marketers index query count does not grow per marketer', function () {
    $service = app(MarketerService::class);

    $seedReferrals = function (Marketer $marketer): void {
        $customer = User::factory()->create();
        attachActiveCustomer($customer);
        attachMarketerReferral($marketer, $customer);

        $merchant = User::factory()->create();
        attachMerchantMembership($merchant, Merchant::factory()->create());
        attachMarketerReferral($marketer, $merchant);
    };

    $first = Marketer::factory()->create();
    $seedReferrals($first);

    $oneQueries = captureMarketerIndexQueries(
        fn () => $service->getPaginatedMarketers(perPage: 15)
    );

    Marketer::factory()->count(9)->create()->each($seedReferrals);

    $tenQueries = captureMarketerIndexQueries(
        fn () => $service->getPaginatedMarketers(perPage: 15)
    );

    $oneCount = count($oneQueries);
    $tenCount = count($tenQueries);
    $referralRowLoads = collect($tenQueries)->filter(function (array $query) {
        $sql = strtolower($query['query']);

        return str_contains($sql, 'marketer_referrals')
            && ! str_contains($sql, 'count(');
    })->count();

    expect($referralRowLoads)->toBe(0)
        ->and($tenCount - $oneCount)->toBeLessThan(3)
        ->and($tenCount - $oneCount)->toBeLessThan(9);
});
