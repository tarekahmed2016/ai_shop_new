<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantOfferCreditService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;

function secondMatchedRequest(Merchant $merchant, Category $category): CustomerRequest
{
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'Need a second part',
    ]);

    app(RequestMatchingService::class)->sync($request);

    return $request;
}

test('free mode allows offer submission with zero balance and does not deduct', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect(route('merchant.requests.show', $request));

    expect(MerchantOffer::query()->count())->toBe(1)
        ->and(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(0)
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->count())->toBe(0);
});

test('free mode still blocks a second submitted offer for the same request', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload(['price' => '40.000']))
        ->assertSessionHasErrors('price');

    expect(MerchantOffer::query()->count())->toBe(1)
        ->and(MerchantOfferCreditTransaction::query()->count())->toBe(0);
});

test('enforced mode deducts one credit on first submit and rejects the next new request at zero', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'category' => $category] = matchedOfferSetup();
    enableOfferCreditEnforcement();
    $admin = creditAdmin();

    app(MerchantOfferCreditService::class)->addCredits(
        $merchant,
        1,
        TransactionSource::BankTransfer,
        'TRX-1',
        'top up',
        $admin,
    );

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect(route('merchant.requests.show', $request));

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(0)
        ->and(MerchantOfferCreditTransaction::query()->where('merchant_id', $merchant->id)->where('type', TransactionType::OfferSubmit)->count())->toBe(1);

    $second = secondMatchedRequest($merchant, $category);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $second), offerPayload())
        ->assertSessionHasErrors('credits');

    expect(MerchantOffer::query()->where('customer_request_id', $second->id)->exists())->toBeFalse()
        ->and(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(0);
});

test('withdraw does not refund and resubmit does not deduct a second credit', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();
    enableOfferCreditEnforcement();
    $admin = creditAdmin();
    app(MerchantOfferCreditService::class)->addCredits($merchant, 5, TransactionSource::Cash, null, null, $admin);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(4);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.withdraw', $request))
        ->assertRedirect();

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(4)
        ->and(MerchantOffer::query()->value('status'))->toBe(OfferStatus::Withdrawn)
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->count())->toBe(1);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload(['price' => '33.000']))
        ->assertRedirect();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload(['price' => '34.000']))
        ->assertSessionHasErrors('price');

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(4)
        ->and(MerchantOffer::query()->count())->toBe(1)
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->count())->toBe(1);
});

test('validation failure and unauthorized submit do not deduct credits', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();
    enableOfferCreditEnforcement();
    $admin = creditAdmin();
    app(MerchantOfferCreditService::class)->addCredits($merchant, 2, TransactionSource::Other, null, null, $admin);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), [])
        ->assertSessionHasErrors('price');

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(2)
        ->and(MerchantOffer::query()->exists())->toBeFalse();

    $staff = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $staff->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Staff,
        'status' => MembershipStatus::Active,
    ]);
    $staffMembership = MerchantUser::query()->where('user_id', $staff->id)->where('merchant_id', $merchant->id)->firstOrFail();
    app(MerchantPermissionService::class)->syncPermissions($staffMembership, [PermissionKey::OffersView->value]);

    $this->actingAs($staff)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertForbidden();

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(2);
});

test('failed offer mutation after lock does not deduct credits', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();
    enableOfferCreditEnforcement();
    $admin = creditAdmin();
    app(MerchantOfferCreditService::class)->addCredits($merchant, 1, TransactionSource::Cash, null, null, $admin);

    $request->status = RequestStatus::Closed;
    $request->save();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertSessionHasErrors('price');

    expect(MerchantOffer::query()->exists())->toBeFalse()
        ->and(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(1);
});

test('offer submit unique consumption is enforced at the database', function () {
    $merchant = Merchant::factory()->create();
    $request = CustomerRequest::factory()->create();
    $offer = MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
    ]);

    MerchantOfferCreditTransaction::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
        'merchant_offer_id' => $offer->id,
        'type' => TransactionType::OfferSubmit,
        'source' => TransactionSource::OfferSubmit,
        'amount' => -1,
    ]);

    expect(fn () => MerchantOfferCreditTransaction::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
        'merchant_offer_id' => $offer->id,
        'type' => TransactionType::OfferSubmit,
        'source' => TransactionSource::OfferSubmit,
        'amount' => -1,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->count())->toBe(1);
});

test('sequential submissions cannot spend the last credit twice', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request, 'category' => $category] = matchedOfferSetup();
    enableOfferCreditEnforcement();
    $admin = creditAdmin();
    app(MerchantOfferCreditService::class)->addCredits($merchant, 1, TransactionSource::BankTransfer, null, null, $admin);
    $second = secondMatchedRequest($merchant, $category);

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $request), offerPayload())
        ->assertRedirect();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->post(route('merchant.requests.offers.store', $second), offerPayload())
        ->assertSessionHasErrors('credits');

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(0)
        ->and(MerchantOffer::query()->count())->toBe(1);
});

test('merchant home and request show expose credit snapshot without changing usage metrics', function () {
    ['merchant' => $merchant, 'user' => $user, 'request' => $request] = matchedOfferSetup();

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->get(route('merchant.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.requests_received', 1)
            ->where('usage.offers_submitted', 0)
            ->where('offerCredits.enforcement_enabled', false)
            ->where('offerCredits.balance', 0)
            ->where('offerCredits.can_consume_new', true));

    $this->actingAs($user)
        ->withSession(offerSession($merchant))
        ->get(route('merchant.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offerCredits.enforcement_enabled', false)
            ->where('offerCredits.can_consume_new', true));
});
