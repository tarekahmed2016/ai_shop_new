<?php

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantRequestMatch;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Services\MerchantService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

function merchantsIndexAdmin(): User
{
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

/**
 * @param  callable(): mixed  $callback
 * @return list<array{query: string, bindings: array<int, mixed>, time: float|null}>
 */
function captureMerchantAdminIndexQueries(callable $callback): array
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
function merchantsIndexRow(array $rows, Merchant $merchant): array
{
    $row = collect($rows)->first(
        fn (array $row) => ($row['public_id'] ?? null) === $merchant->public_id
    );

    expect($row)->not->toBeNull();

    return $row;
}

function trackedSubmittedOffer(Merchant $merchant, ?MerchantRequestMatch $match = null): MerchantOffer
{
    $match ??= MerchantRequestMatch::factory()->create(['merchant_id' => $merchant->id]);

    return MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $match->customer_request_id,
        'status' => OfferStatus::Submitted,
        'submitted_at' => now(),
        'withdrawn_at' => null,
    ]);
}

test('offer submission rate uses loaded counts, rounds, and is not capped at 100 percent', function () {
    $cases = [
        [0, 0, 0],
        [20, 5, 25],
        [3, 2, 67],
        [10, 10, 100],
        [2, 5, 250],
        [0, 5, 0],
    ];

    foreach ($cases as [$received, $submitted, $expected]) {
        $merchant = new Merchant;
        $merchant->setAttribute('requests_received_count', $received);
        $merchant->setAttribute('offers_submitted_count', $submitted);

        expect($merchant->offerSubmissionRate())->toBe($expected);
    }
});

test('admin merchants index counts only tracked currently submitted responses', function () {
    $admin = merchantsIndexAdmin();

    $zero = Merchant::factory()->create(['name' => 'Zero Usage Shop']);

    $legacy = Merchant::factory()->create(['name' => 'Legacy Offers Shop']);
    MerchantOffer::factory()->count(10)->create(['merchant_id' => $legacy->id, 'submitted_at' => now()]);
    $legacyTracked = MerchantRequestMatch::factory()->create(['merchant_id' => $legacy->id]);

    $responded = Merchant::factory()->create(['name' => 'Responded Shop']);
    MerchantOffer::factory()->count(10)->create(['merchant_id' => $responded->id, 'submitted_at' => now()]);
    $respondedMatch = MerchantRequestMatch::factory()->create(['merchant_id' => $responded->id]);
    trackedSubmittedOffer($responded, $respondedMatch);

    $withdrawnShop = Merchant::factory()->create(['name' => 'Withdrawn Shop']);
    $withdrawnMatch = MerchantRequestMatch::factory()->create(['merchant_id' => $withdrawnShop->id]);
    $withdrawnOffer = trackedSubmittedOffer($withdrawnShop, $withdrawnMatch);

    $counted = Merchant::factory()->create(['name' => 'Counted Shop']);
    $countedMatch = MerchantRequestMatch::factory()->create(['merchant_id' => $counted->id]);
    MerchantRequestMatch::factory()->create(['merchant_id' => $counted->id]);
    trackedSubmittedOffer($counted, $countedMatch);
    MerchantOffer::factory()->withdrawn()->create(['merchant_id' => $counted->id, 'submitted_at' => now()]);
    MerchantOffer::factory()->invalidated()->create(['merchant_id' => $counted->id, 'submitted_at' => now()]);

    $other = Merchant::factory()->create(['name' => 'Other Shop']);
    $otherMatch = MerchantRequestMatch::factory()->create(['merchant_id' => $other->id]);
    trackedSubmittedOffer($other, $otherMatch);

    $response = $this->actingAs($admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantsPage', false)
            ->has('merchants.data', 6));

    $rows = $response->inertiaProps('merchants.data');

    $zeroRow = merchantsIndexRow($rows, $zero);
    expect((int) $zeroRow['requests_received_count'])->toBe(0)
        ->and((int) $zeroRow['offers_submitted_count'])->toBe(0)
        ->and((int) $zeroRow['offer_submission_rate'])->toBe(0);

    $legacyRow = merchantsIndexRow($rows, $legacy);
    expect((int) $legacyRow['requests_received_count'])->toBe(1)
        ->and((int) $legacyRow['offers_submitted_count'])->toBe(0)
        ->and((int) $legacyRow['offer_submission_rate'])->toBe(0);

    $respondedRow = merchantsIndexRow($rows, $responded);
    expect((int) $respondedRow['requests_received_count'])->toBe(1)
        ->and((int) $respondedRow['offers_submitted_count'])->toBe(1)
        ->and((int) $respondedRow['offer_submission_rate'])->toBe(100);

    $withdrawnRow = merchantsIndexRow($rows, $withdrawnShop);
    expect((int) $withdrawnRow['requests_received_count'])->toBe(1)
        ->and((int) $withdrawnRow['offers_submitted_count'])->toBe(1)
        ->and((int) $withdrawnRow['offer_submission_rate'])->toBe(100);

    $withdrawnOffer->update([
        'status' => OfferStatus::Withdrawn,
        'withdrawn_at' => now(),
    ]);

    $afterWithdraw = merchantsIndexRow(
        $this->actingAs($admin)->get(route('merchants.index'))->inertiaProps('merchants.data'),
        $withdrawnShop,
    );
    expect((int) $afterWithdraw['offers_submitted_count'])->toBe(0)
        ->and((int) $afterWithdraw['offer_submission_rate'])->toBe(0);

    $withdrawnOffer->update([
        'status' => OfferStatus::Submitted,
        'withdrawn_at' => null,
    ]);

    $afterResubmit = merchantsIndexRow(
        $this->actingAs($admin)->get(route('merchants.index'))->inertiaProps('merchants.data'),
        $withdrawnShop,
    );
    expect((int) $afterResubmit['offers_submitted_count'])->toBe(1)
        ->and((int) $afterResubmit['offer_submission_rate'])->toBe(100);

    $countedRow = merchantsIndexRow($rows, $counted);
    expect((int) $countedRow['requests_received_count'])->toBe(2)
        ->and((int) $countedRow['offers_submitted_count'])->toBe(1)
        ->and((int) $countedRow['offer_submission_rate'])->toBe(50);

    $otherRow = merchantsIndexRow($rows, $other);
    expect((int) $otherRow['requests_received_count'])->toBe(1)
        ->and((int) $otherRow['offers_submitted_count'])->toBe(1)
        ->and((int) $otherRow['offer_submission_rate'])->toBe(100);
});

test('one offer per merchant and request is enforced so response rate is not inflated', function () {
    $merchant = Merchant::factory()->create();
    $match = MerchantRequestMatch::factory()->create(['merchant_id' => $merchant->id]);
    trackedSubmittedOffer($merchant, $match);

    expect(fn () => MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $match->customer_request_id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    $row = merchantsIndexRow(
        $this->actingAs(merchantsIndexAdmin())->get(route('merchants.index'))->inertiaProps('merchants.data'),
        $merchant,
    );

    expect((int) $row['requests_received_count'])->toBe(1)
        ->and((int) $row['offers_submitted_count'])->toBe(1)
        ->and((int) $row['offer_submission_rate'])->toBe(100);
});

test('admin merchants index falls back to owner email without N+1', function () {
    app(MerchantPermissionService::class)->seedCatalog();
    $admin = merchantsIndexAdmin();

    $withBusinessEmail = Merchant::factory()->create([
        'name' => 'Business Email Shop',
        'email' => 'shop@example.test',
    ]);
    $ignoredOwner = User::factory()->create(['email' => 'ignored-owner@example.test']);
    MerchantUser::factory()->owner()->create([
        'merchant_id' => $withBusinessEmail->id,
        'user_id' => $ignoredOwner->id,
        'status' => MembershipStatus::Active,
    ]);

    $ownerOnly = Merchant::factory()->create([
        'name' => 'Owner Email Shop',
        'email' => null,
    ]);
    $owner = User::factory()->create(['email' => 'owner-fallback@example.test']);
    MerchantUser::factory()->owner()->create([
        'merchant_id' => $ownerOnly->id,
        'user_id' => $owner->id,
        'status' => MembershipStatus::Active,
    ]);
    $staff = User::factory()->create(['email' => 'staff-not-used@example.test']);
    MerchantUser::factory()->create([
        'merchant_id' => $ownerOnly->id,
        'user_id' => $staff->id,
        'role' => Role::Staff,
        'status' => MembershipStatus::Active,
    ]);

    $neither = Merchant::factory()->create([
        'name' => 'No Email Shop',
        'email' => '',
    ]);
    $staffOnly = User::factory()->create(['email' => 'staff-only@example.test']);
    MerchantUser::factory()->create([
        'merchant_id' => $neither->id,
        'user_id' => $staffOnly->id,
        'role' => Role::Staff,
        'status' => MembershipStatus::Active,
    ]);

    $rows = $this->actingAs($admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->inertiaProps('merchants.data');

    expect(merchantsIndexRow($rows, $withBusinessEmail)['display_email'])->toBe('shop@example.test')
        ->and(merchantsIndexRow($rows, $ownerOnly)['display_email'])->toBe('owner-fallback@example.test')
        ->and(merchantsIndexRow($rows, $neither)['display_email'])->toBeNull();
});

test('admin merchants index query count does not grow per merchant', function () {
    app(MerchantPermissionService::class)->seedCatalog();
    $service = app(MerchantService::class);

    $first = Merchant::factory()->create(['email' => null]);
    MerchantUser::factory()->owner()->create([
        'merchant_id' => $first->id,
        'user_id' => User::factory()->create(['email' => 'first-owner@example.test'])->id,
    ]);
    $firstMatch = MerchantRequestMatch::factory()->create(['merchant_id' => $first->id]);
    trackedSubmittedOffer($first, $firstMatch);

    $oneQueries = captureMerchantAdminIndexQueries(
        fn () => $service->getPaginatedMerchants(perPage: 15)
    );

    Merchant::factory()->count(9)->create(['email' => null])->each(function (Merchant $merchant) {
        MerchantUser::factory()->owner()->create([
            'merchant_id' => $merchant->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $match = MerchantRequestMatch::factory()->create(['merchant_id' => $merchant->id]);
        trackedSubmittedOffer($merchant, $match);
    });

    $tenQueries = captureMerchantAdminIndexQueries(
        fn () => $service->getPaginatedMerchants(perPage: 15)
    );

    $rowLoads = collect($tenQueries)->filter(function (array $query) {
        $sql = strtolower($query['query']);

        return (str_contains($sql, 'merchant_request_matches') || str_contains($sql, 'merchant_offers'))
            && ! str_contains($sql, 'count(')
            && ! str_contains($sql, 'exists');
    })->count();

    expect($rowLoads)->toBe(0)
        ->and(count($tenQueries) - count($oneQueries))->toBeLessThan(3)
        ->and(count($tenQueries) - count($oneQueries))->toBeLessThan(9);
});
