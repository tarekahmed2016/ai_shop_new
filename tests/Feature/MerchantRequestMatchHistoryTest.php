<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\Marketers\Status as MarketerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Marketer;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantRequestMatch;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MatchedRequestPushDispatcher;
use App\Services\MerchantCategoryService;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use App\Services\MerchantRequestMatchService;
use App\Services\RequestMatchingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
    Notification::fake();
});

function historySession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

function historyMembership(User $user, Merchant $merchant, Role $role = Role::Owner): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => MembershipStatus::Active,
    ]);
}

function historyAssign(Merchant $merchant, Category $category): MerchantCategory
{
    return MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
}

function historyRequest(Category $category): CustomerRequest
{
    return CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
}

function historyOffer(Merchant $merchant, OfferStatus $status = OfferStatus::Submitted): MerchantOffer
{
    return MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'status' => $status,
        'submitted_at' => now(),
        'withdrawn_at' => $status === OfferStatus::Withdrawn ? now() : null,
    ]);
}

/**
 * @param  callable(): mixed  $callback
 * @return list<array{query: string, bindings: array<int, mixed>, time: float|null}>
 */
function captureMerchantUsageQueries(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return $log;
}

test('matching a request creates a persistent historical match', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    historyAssign($merchant, $category);
    $request = historyRequest($category);

    app(RequestMatchingService::class)->sync($request);

    expect(MerchantRequestMatch::query()->count())->toBe(1)
        ->and(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $request->id)->exists())->toBeTrue()
        ->and(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->value('matched_category_id'))->toBe($category->id);
});

test('non matching merchant receives no historical row', function () {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $matched = Merchant::factory()->create();
    $unmatched = Merchant::factory()->create();
    historyAssign($matched, $category);
    historyAssign($unmatched, $otherCategory);
    $request = historyRequest($category);

    app(RequestMatchingService::class)->sync($request);

    expect(MerchantRequestMatch::query()->where('merchant_id', $matched->id)->exists())->toBeTrue()
        ->and(MerchantRequestMatch::query()->where('merchant_id', $unmatched->id)->exists())->toBeFalse()
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($unmatched->id))->toBe(0);
});

test('multiple merchants create independent historical rows', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $merchantC = Merchant::factory()->create();
    historyAssign($merchantA, $category);
    historyAssign($merchantB, $category);
    historyAssign($merchantC, $category);
    $request = historyRequest($category);

    app(RequestMatchingService::class)->sync($request);

    expect(MerchantRequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(3)
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchantA->id))->toBe(1)
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchantB->id))->toBe(1)
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchantC->id))->toBe(1);
});

test('duplicate distribution keeps one historical row per merchant and request', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    historyAssign($merchant, $category);
    $request = historyRequest($category);
    $service = app(RequestMatchingService::class);

    $service->sync($request);
    $service->sync($request->fresh());
    $service->sync($request->fresh());

    expect(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchant->id))->toBe(1);

    expect(fn () => MerchantRequestMatch::query()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
        'matched_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('push failure does not prevent or remove the historical match', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    historyAssign($merchant, $category);
    $request = historyRequest($category);

    $dispatcher = Mockery::mock(MatchedRequestPushDispatcher::class)->makePartial();
    $dispatcher->shouldReceive('notify')->andThrow(new RuntimeException('push failed'));
    $this->app->instance(MatchedRequestPushDispatcher::class, $dispatcher);

    app(RequestMatchingService::class)->sync($request);

    expect(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $request->id)->exists())->toBeTrue()
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchant->id))->toBe(1);
});

test('merchant with no push subscription still receives a historical match', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    historyAssign($merchant, $category);
    $user = User::factory()->create();
    historyMembership($user, $merchant);
    $request = historyRequest($category);

    expect($user->pushSubscriptions()->count())->toBe(0);

    app(RequestMatchingService::class)->sync($request);

    expect(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->exists())->toBeTrue()
        ->and(RequestMatch::query()->where('merchant_id', $merchant->id)->exists())->toBeTrue();
});

test('historical match survives category removal while live match is removed', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    historyMembership($user, $merchant);
    $assignment = historyAssign($merchant, $category);
    $request = historyRequest($category);

    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->where('merchant_id', $merchant->id)->count())->toBe(1)
        ->and(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->count())->toBe(1);

    $this->actingAs($user);
    app(MerchantCategoryService::class)->detach($merchant, $assignment);

    expect(RequestMatch::query()->where('merchant_id', $merchant->id)->count())->toBe(0)
        ->and(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchant->id))->toBe(1);
});

test('adding a category does not backfill old unmatched requests', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    historyMembership($user, $merchant);
    historyAssign($merchant, $categoryA);

    $oldRequest = historyRequest($categoryB);
    app(RequestMatchingService::class)->sync($oldRequest);

    expect(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $oldRequest->id)->exists())->toBeFalse();

    $this->actingAs($user);
    app(MerchantCategoryService::class)->attach($merchant, $categoryB->public_id);

    expect(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $oldRequest->id)->exists())->toBeFalse()
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchant->id))->toBe(0);
});

test('new request after category addition creates a historical match', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    historyMembership($user, $merchant);
    historyAssign($merchant, $categoryA);

    $this->actingAs($user);
    app(MerchantCategoryService::class)->attach($merchant, $categoryB->public_id);

    $newRequest = historyRequest($categoryB);
    app(RequestMatchingService::class)->sync($newRequest);

    expect(MerchantRequestMatch::query()->where('merchant_id', $merchant->id)->where('customer_request_id', $newRequest->id)->exists())->toBeTrue()
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchant->id))->toBe(1);
});

test('merchant home and dashboard counters use active merchant context only', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $user = User::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    historyMembership($user, $merchantA);
    historyMembership($user, $merchantB);
    historyAssign($merchantA, $categoryA);
    historyAssign($merchantB, $categoryB);

    app(RequestMatchingService::class)->sync(historyRequest($categoryA));
    app(RequestMatchingService::class)->sync(historyRequest($categoryA));
    app(RequestMatchingService::class)->sync(historyRequest($categoryB));

    historyOffer($merchantA);
    historyOffer($merchantA);
    historyOffer($merchantA);
    historyOffer($merchantA);
    historyOffer($merchantA);
    historyOffer($merchantB);
    historyOffer($merchantB);
    historyOffer($merchantB);

    Customer::factory()->create(['user_id' => $user->id, 'status' => CustomerStatus::Active]);
    Marketer::factory()->create(['user_id' => $user->id, 'status' => MarketerStatus::Active]);

    $this->actingAs($user)
        ->withSession(historySession($merchantA))
        ->get(route('merchant.home', [
            'merchant_id' => $merchantB->id,
            'requests_received' => 999,
            'offers_submitted' => 999,
            'match_count' => 999,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantHomePage', false)
            ->where('usage.requests_received', 2)
            ->where('usage.offers_submitted', 5)
            ->missing('usage.0.merchant_id'));

    $this->actingAs($user)
        ->withSession(historySession($merchantA))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/IndexPage', false)
            ->where('merchantWorkspace.requests_received', 2)
            ->where('merchantWorkspace.offers_submitted', 5));

    $this->actingAs($user)
        ->withSession(historySession($merchantB))
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.requests_received', 1)
            ->where('usage.offers_submitted', 3));

    $this->actingAs($user)
        ->withSession(historySession($merchantB))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('merchantWorkspace.requests_received', 1)
            ->where('merchantWorkspace.offers_submitted', 3));
});

test('withdrawn and invalidated offers remain in offers submitted history', function () {
    $merchant = Merchant::factory()->create();
    historyOffer($merchant, OfferStatus::Submitted);
    historyOffer($merchant, OfferStatus::Submitted);
    historyOffer($merchant, OfferStatus::Withdrawn);
    historyOffer($merchant, OfferStatus::Invalidated);

    expect(app(MerchantRequestMatchService::class)->offersSubmittedCount($merchant->id))->toBe(4);
});

test('another merchants matches and offers are excluded from counters', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    historyAssign($merchantA, $category);
    historyAssign($merchantB, $category);
    app(RequestMatchingService::class)->sync(historyRequest($category));
    historyOffer($merchantB);

    expect(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchantA->id))->toBe(1)
        ->and(app(MerchantRequestMatchService::class)->offersSubmittedCount($merchantA->id))->toBe(0)
        ->and(app(MerchantRequestMatchService::class)->requestsReceivedCount($merchantB->id))->toBe(1)
        ->and(app(MerchantRequestMatchService::class)->offersSubmittedCount($merchantB->id))->toBe(1);
});

test('merchant usage counters use sql aggregates and stay constant with more history', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();
    historyMembership($user, $merchant);

    MerchantRequestMatch::factory()->create(['merchant_id' => $merchant->id]);
    historyOffer($merchant);

    $this->actingAs($user)->withSession(historySession($merchant))->get(route('merchant.home'))->assertOk();

    $one = captureMerchantUsageQueries(function () use ($user, $merchant) {
        $this->actingAs($user)->withSession(historySession($merchant))->get(route('merchant.home'))->assertOk();
    });

    MerchantRequestMatch::factory()->count(12)->create(['merchant_id' => $merchant->id]);
    MerchantOffer::factory()->count(8)->create(['merchant_id' => $merchant->id, 'submitted_at' => now()]);

    $many = captureMerchantUsageQueries(function () use ($user, $merchant) {
        $this->actingAs($user)->withSession(historySession($merchant))->get(route('merchant.home'))->assertOk();
    });

    $rowLoads = collect($many)->filter(function (array $query) {
        $sql = strtolower($query['query']);

        return (str_contains($sql, 'merchant_request_matches') || str_contains($sql, 'merchant_offers'))
            && ! str_contains($sql, 'count(');
    })->count();

    expect($rowLoads)->toBe(0)
        ->and(count($many) - count($one))->toBeLessThan(3);
});
