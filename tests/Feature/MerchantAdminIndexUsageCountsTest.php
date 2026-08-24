<?php

use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantRequestMatch;
use App\Models\User;
use App\Services\MerchantService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function merchantsIndexAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
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

test('admin merchants index shows historical usage counts per merchant', function () {
    $admin = merchantsIndexAdmin();

    $zero = Merchant::factory()->create(['name' => 'Zero Usage Shop']);

    $legacyOffersOnly = Merchant::factory()->create(['name' => 'Legacy Offers Shop']);
    MerchantOffer::factory()->count(5)->create(['merchant_id' => $legacyOffersOnly->id, 'submitted_at' => now()]);

    $counted = Merchant::factory()->create(['name' => 'Counted Shop']);
    MerchantRequestMatch::factory()->count(2)->create(['merchant_id' => $counted->id]);
    MerchantOffer::factory()->create(['merchant_id' => $counted->id, 'submitted_at' => now()]);
    MerchantOffer::factory()->withdrawn()->create(['merchant_id' => $counted->id, 'submitted_at' => now()]);
    MerchantOffer::factory()->invalidated()->create(['merchant_id' => $counted->id, 'submitted_at' => now()]);
    MerchantOffer::factory()->create([
        'merchant_id' => $counted->id,
        'status' => OfferStatus::Submitted,
        'submitted_at' => null,
    ]);

    $other = Merchant::factory()->create(['name' => 'Other Shop']);
    MerchantRequestMatch::factory()->count(4)->create(['merchant_id' => $other->id]);
    MerchantOffer::factory()->count(7)->create(['merchant_id' => $other->id, 'submitted_at' => now()]);

    $rounded = Merchant::factory()->create(['name' => 'Rounded Rate Shop']);
    MerchantRequestMatch::factory()->count(3)->create(['merchant_id' => $rounded->id]);
    MerchantOffer::factory()->count(2)->create(['merchant_id' => $rounded->id, 'submitted_at' => now()]);

    $response = $this->actingAs($admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantsPage', false)
            ->has('merchants.data', 5));

    $rows = $response->inertiaProps('merchants.data');

    $zeroRow = merchantsIndexRow($rows, $zero);
    expect((int) $zeroRow['requests_received_count'])->toBe(0)
        ->and((int) $zeroRow['offers_submitted_count'])->toBe(0)
        ->and((int) $zeroRow['offer_submission_rate'])->toBe(0);

    $legacyRow = merchantsIndexRow($rows, $legacyOffersOnly);
    expect((int) $legacyRow['requests_received_count'])->toBe(0)
        ->and((int) $legacyRow['offers_submitted_count'])->toBe(5)
        ->and((int) $legacyRow['offer_submission_rate'])->toBe(0);

    $countedRow = merchantsIndexRow($rows, $counted);
    expect((int) $countedRow['requests_received_count'])->toBe(2)
        ->and((int) $countedRow['offers_submitted_count'])->toBe(3)
        ->and((int) $countedRow['offer_submission_rate'])->toBe(150);

    $roundedRow = merchantsIndexRow($rows, $rounded);
    expect((int) $roundedRow['requests_received_count'])->toBe(3)
        ->and((int) $roundedRow['offers_submitted_count'])->toBe(2)
        ->and((int) $roundedRow['offer_submission_rate'])->toBe(67);

    $otherRow = merchantsIndexRow($rows, $other);
    expect((int) $otherRow['requests_received_count'])->toBe(4)
        ->and((int) $otherRow['offers_submitted_count'])->toBe(7)
        ->and((int) $otherRow['offer_submission_rate'])->toBe(175);
});

test('admin merchants index query count does not grow per merchant', function () {
    $service = app(MerchantService::class);

    $first = Merchant::factory()->create();
    MerchantRequestMatch::factory()->count(3)->create(['merchant_id' => $first->id]);
    MerchantOffer::factory()->count(2)->create(['merchant_id' => $first->id, 'submitted_at' => now()]);

    $oneQueries = captureMerchantAdminIndexQueries(
        fn () => $service->getPaginatedMerchants(perPage: 15)
    );

    Merchant::factory()->count(9)->create()->each(function (Merchant $merchant) {
        MerchantRequestMatch::factory()->create(['merchant_id' => $merchant->id]);
        MerchantOffer::factory()->create(['merchant_id' => $merchant->id, 'submitted_at' => now()]);
    });

    $tenQueries = captureMerchantAdminIndexQueries(
        fn () => $service->getPaginatedMerchants(perPage: 15)
    );

    $rowLoads = collect($tenQueries)->filter(function (array $query) {
        $sql = strtolower($query['query']);

        return (str_contains($sql, 'merchant_request_matches') || str_contains($sql, 'merchant_offers'))
            && ! str_contains($sql, 'count(');
    })->count();

    expect($rowLoads)->toBe(0)
        ->and(count($tenQueries) - count($oneQueries))->toBeLessThan(3)
        ->and(count($tenQueries) - count($oneQueries))->toBeLessThan(9);
});
