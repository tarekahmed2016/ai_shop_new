<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function offerImageSession(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

/**
 * @return array{offer: MerchantOffer, image: MerchantOfferImage, requestOwner: Customer, request: CustomerRequest}
 */
function seededOfferImage(Merchant $merchant, ?Customer $requestOwner = null): array
{
    $requestOwner ??= Customer::factory()->create();
    $request = CustomerRequest::factory()->create([
        'customer_id' => $requestOwner->id,
    ]);

    $offer = MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
    ]);

    $path = 'merchant-offers/'.fake()->uuid().'.jpg';
    Storage::disk(MerchantOfferImage::DISK)->put($path, 'offer-image-bytes');

    $image = MerchantOfferImage::factory()->create([
        'merchant_offer_id' => $offer->id,
        'path' => $path,
    ]);

    return compact('offer', 'image', 'requestOwner', 'request');
}

function merchantOwner(Merchant $merchant, ?User $user = null): User
{
    $user ??= User::factory()->create();

    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);

    return $user;
}

test('dual user with correct merchant context can view that merchant offer image on another customers request', function () {
    $merchant = Merchant::factory()->create();
    $user = merchantOwner($merchant);
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($merchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertOk();
});

test('dual user with foreign merchant context cannot view another merchants offer image', function () {
    $offerMerchant = Merchant::factory()->create();
    $otherMerchant = Merchant::factory()->create();
    $user = merchantOwner($offerMerchant);
    merchantOwner($otherMerchant, $user);
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($offerMerchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($otherMerchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertForbidden();
});

test('dual user without customer ownership or matching merchant context is denied', function () {
    $offerMerchant = Merchant::factory()->create();
    $otherMerchant = Merchant::factory()->create();
    $user = merchantOwner($otherMerchant);
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($offerMerchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($otherMerchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertForbidden();
});

test('customer-only user can view submitted offer image on their own request', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant, $customer);

    $this->actingAs($user)
        ->get(route('customer.offers.images.show', [$offer, $image]))
        ->assertOk();
});

test('customer-only user cannot view offer image on another customers request', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant);

    $this->actingAs($user)
        ->get(route('customer.offers.images.show', [$offer, $image]))
        ->assertForbidden();
});

test('merchant-only user can view their merchant offer image', function () {
    $merchant = Merchant::factory()->create();
    $user = merchantOwner($merchant);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($merchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertOk();
});

test('merchant-only user cannot view a foreign merchant offer image', function () {
    $offerMerchant = Merchant::factory()->create();
    $otherMerchant = Merchant::factory()->create();
    $user = merchantOwner($otherMerchant);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($offerMerchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($otherMerchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertForbidden();
});

test('inactive customer does not grant customer-path access to an offer image', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Inactive,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant, $customer);

    $this->actingAs($user)
        ->get(route('customer.offers.images.show', [$offer, $image]))
        ->assertForbidden();
});

test('inactive merchant membership cannot access merchant offer images', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Inactive,
    ]);
    Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($merchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertRedirect(route('merchant.select'));
});

test('inactive merchant cannot be used as merchant context for offer images', function () {
    $merchant = Merchant::factory()->inactive()->create();
    $user = merchantOwner($merchant);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($merchant);

    $this->actingAs($user)
        ->withSession(offerImageSession($merchant))
        ->get(route('merchant.offers.images.show', [$offer, $image]))
        ->assertRedirect(route('merchant.select'));
});

test('dual user can still view own customer offer image while in an unrelated merchant context', function () {
    $offerMerchant = Merchant::factory()->create();
    $ownMerchant = Merchant::factory()->create();
    $user = merchantOwner($ownMerchant);
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => CustomerStatus::Active,
    ]);

    ['offer' => $offer, 'image' => $image] = seededOfferImage($offerMerchant, $customer);

    $this->actingAs($user)
        ->withSession(offerImageSession($ownMerchant))
        ->get(route('customer.offers.images.show', [$offer, $image]))
        ->assertOk();
});
