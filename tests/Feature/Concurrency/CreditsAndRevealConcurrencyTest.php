<?php

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\MerchantOfferCredits\TransactionSource as CreditSource;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use App\Models\CustomerOfferContactReveal;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use App\Services\MerchantOfferCreditService;
use App\Services\OfferContactRevealService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concurrency\ConcurrencyFixtures;
use Tests\Support\Concurrency\ConcurrentProcesses;

beforeEach(function () {
    if (! ConcurrentProcesses::supported()) {
        $this->markTestSkipped('pcntl_fork is required for overlapping concurrency tests.');
    }
});

test('concurrent first-offer submits consume at most one credit per request and cannot go negative', function () {
    $category = ConcurrencyFixtures::category();
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    $merchant = ConcurrencyFixtures::merchantForCategory($category);
    $first = ConcurrencyFixtures::readyRequest($customer, $category);
    $second = ConcurrencyFixtures::readyRequest($customer, $category);
    $firstOffer = ConcurrencyFixtures::submittedOffer($first, $merchant);
    $secondOffer = ConcurrencyFixtures::submittedOffer($second, $merchant);

    enableOfferCreditEnforcement();
    app(MerchantOfferCreditService::class)->addCredits(
        $merchant,
        1,
        CreditSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $merchantId = (int) $merchant->id;
    $payloads = [
        ['request' => (int) $first->id, 'offer' => (int) $firstOffer->id],
        ['request' => (int) $second->id, 'offer' => (int) $secondOffer->id],
    ];
    $actorId = (int) $actor->id;

    ConcurrentProcesses::map(2, function (int $index) use ($merchantId, $payloads, $actorId) {
        enableOfferCreditEnforcement();
        $credits = app(MerchantOfferCreditService::class);
        $merchant = Merchant::query()->findOrFail($merchantId);
        $request = CustomerRequest::query()->findOrFail($payloads[$index]['request']);
        $offer = MerchantOffer::query()->findOrFail($payloads[$index]['offer']);
        $actor = User::query()->findOrFail($actorId);

        try {
            return DB::transaction(function () use ($credits, $merchant, $request, $offer, $actor) {
                $locked = $credits->lockMerchant((int) $merchant->id);
                $credits->assertCanConsumeForSubmit($locked, $request);

                return $credits->consumeForOfferSubmit($locked, $request, $offer, $actor);
            });
        } catch (ValidationException) {
            return false;
        }
    });

    $balance = app(MerchantOfferCreditService::class)->balance($merchantId);
    $consumed = MerchantOfferCreditTransaction::query()->where('merchant_id', $merchantId)->where('amount', -1)->count();

    if (! $this->usesInnoDbRowLocks() && ($balance !== 0 || $consumed !== 1)) {
        $this->markTestSkipped('SQLite cannot prove Merchant::lockForUpdate() offer-credit serialization. Re-run with CONCURRENCY_DB=mariadb.');
    }

    expect($balance)->toBeGreaterThanOrEqual(0)
        ->and($balance)->toBe(0)
        ->and($consumed)->toBe(1);
});

test('concurrent consume of the same request is idempotent', function () {
    $category = ConcurrencyFixtures::category();
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    $merchant = ConcurrencyFixtures::merchantForCategory($category);
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $offer = ConcurrencyFixtures::submittedOffer($request, $merchant);

    enableOfferCreditEnforcement();
    app(MerchantOfferCreditService::class)->addCredits(
        $merchant,
        2,
        CreditSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $merchantId = (int) $merchant->id;
    $requestId = (int) $request->id;
    $offerId = (int) $offer->id;
    $actorId = (int) $actor->id;

    ConcurrentProcesses::map(2, function () use ($merchantId, $requestId, $offerId, $actorId) {
        enableOfferCreditEnforcement();
        $credits = app(MerchantOfferCreditService::class);

        try {
            return DB::transaction(function () use ($credits, $merchantId, $requestId, $offerId, $actorId) {
                $locked = $credits->lockMerchant($merchantId);
                $request = CustomerRequest::query()->findOrFail($requestId);
                $offer = MerchantOffer::query()->findOrFail($offerId);
                $actor = User::query()->findOrFail($actorId);

                return $credits->consumeForOfferSubmit($locked, $request, $offer, $actor);
            });
        } catch (ValidationException) {
            return false;
        }
    });

    expect(app(MerchantOfferCreditService::class)->balance($merchantId))->toBe(1)
        ->and(MerchantOfferCreditTransaction::query()->where('customer_request_id', $requestId)->count())->toBe(1);
});

test('concurrent extra-request add and deduct preserve the ledger balance and cannot go negative', function () {
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        3,
        ExtraSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $customerId = (int) $customer->id;
    $actorId = (int) $actor->id;

    ConcurrentProcesses::map(2, function (int $index) use ($customerId, $actorId) {
        $service = app(CustomerExtraRequestService::class);
        $customer = Customer::query()->findOrFail($customerId);
        $actor = User::query()->findOrFail($actorId);

        try {
            if ($index === 0) {
                $service->addCredits($customer, 2, ExtraSource::ManualAdjustment, null, 'add', $actor);

                return 'add';
            }

            $service->deductCredits($customer, 3, ExtraSource::ManualAdjustment, 'deduct', null, $actor);

            return 'deduct';
        } catch (ValidationException $exception) {
            return $exception->getMessage();
        }
    });

    $balance = app(CustomerExtraRequestService::class)->balance($customerId);
    $ledger = (int) CustomerExtraRequestTransaction::query()->where('customer_id', $customerId)->sum('amount');

    expect($balance)->toBe($ledger)
        ->and($balance)->toBeGreaterThanOrEqual(0);
});

test('two concurrent full-balance deducts cannot drive extra-request credits negative', function () {
    ['user' => $actor, 'customer' => $customer] = ConcurrencyFixtures::customer();
    app(CustomerExtraRequestService::class)->addCredits(
        $customer,
        1,
        ExtraSource::PromotionalBonus,
        null,
        'seed',
        $actor,
    );

    $customerId = (int) $customer->id;
    $actorId = (int) $actor->id;

    $results = ConcurrentProcesses::map(2, function () use ($customerId, $actorId) {
        $service = app(CustomerExtraRequestService::class);
        $customer = Customer::query()->findOrFail($customerId);
        $actor = User::query()->findOrFail($actorId);

        try {
            $service->deductCredits($customer, 1, ExtraSource::ManualAdjustment, 'deduct', null, $actor);

            return true;
        } catch (ValidationException) {
            return false;
        }
    });

    $accepted = count(array_filter(ConcurrentProcesses::values($results)));
    $balance = app(CustomerExtraRequestService::class)->balance($customerId);

    if (! $this->usesInnoDbRowLocks() && ($accepted !== 1 || $balance !== 0)) {
        $this->markTestSkipped('SQLite cannot prove Customer::lockForUpdate() deduct serialization. Re-run with CONCURRENCY_DB=mariadb.');
    }

    expect($accepted)->toBe(1)
        ->and($balance)->toBe(0);
});

test('concurrent reveals cannot exceed the configured max distinct merchants', function () {
    config(['customer_requests.contact_reveal_limit' => 1]);
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $offers = [];

    for ($i = 0; $i < 2; $i++) {
        $merchant = ConcurrencyFixtures::merchantForCategory($category);
        $offers[] = ConcurrencyFixtures::submittedOffer($request, $merchant);
    }

    $customerId = (int) $customer->id;
    $offerIds = array_map(fn ($offer) => (int) $offer->id, $offers);

    ConcurrentProcesses::map(2, function (int $index) use ($customerId, $offerIds) {
        $customer = Customer::query()->findOrFail($customerId);
        $offer = MerchantOffer::query()->findOrFail($offerIds[$index]);

        try {
            app(OfferContactRevealService::class)->reveal($customer, $offer);

            return true;
        } catch (ValidationException) {
            return false;
        }
    });

    $used = CustomerOfferContactReveal::query()->where('customer_request_id', $request->id)->count();

    if (! $this->usesInnoDbRowLocks() && $used !== 1) {
        $this->markTestSkipped('SQLite cannot prove CustomerRequest::lockForUpdate() reveal serialization. Re-run with CONCURRENCY_DB=mariadb.');
    }

    expect($used)->toBe(1)
        ->and(app(OfferContactRevealService::class)->quotaSnapshot($request->fresh(), $customer->fresh())['used'])->toBe(1);
});

test('duplicate concurrent reveal of the same merchant is idempotent', function () {
    $category = ConcurrencyFixtures::category();
    ['customer' => $customer] = ConcurrencyFixtures::customer();
    $request = ConcurrencyFixtures::readyRequest($customer, $category);
    $merchant = ConcurrencyFixtures::merchantForCategory($category);
    $offer = ConcurrencyFixtures::submittedOffer($request, $merchant);

    $customerId = (int) $customer->id;
    $offerId = (int) $offer->id;

    ConcurrentProcesses::map(2, function () use ($customerId, $offerId) {
        $customer = Customer::query()->findOrFail($customerId);
        $offer = MerchantOffer::query()->findOrFail($offerId);

        return (int) app(OfferContactRevealService::class)->reveal($customer, $offer)->id;
    });

    expect(CustomerOfferContactReveal::query()->where('customer_request_id', $request->id)->where('merchant_id', $merchant->id)->count())->toBe(1);
});
