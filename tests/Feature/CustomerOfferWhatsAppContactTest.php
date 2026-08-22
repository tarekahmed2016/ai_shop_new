<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function whatsappOfferSetup(array $merchantAttrs = []): array
{
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create(array_merge([
        'phone' => '+968 9111-2222',
        'name' => 'Honor Screen Shop',
    ], $merchantAttrs));
    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $owner = User::factory()->create(['phone' => '999000111']);
    MerchantUser::factory()->create([
        'user_id' => $owner->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $customerUser = User::factory()->create([
        'phone' => '0100555666',
    ]);
    $customer = Customer::factory()->create([
        'user_id' => $customerUser->id,
        'phone' => '0100555666',
        'email' => $customerUser->email,
        'whatsapp_id' => 'wa-customer-'.(string) Str::ulid(),
        'name' => $customerUser->name,
    ]);

    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);
    app(RequestMatchingService::class)->sync($request);

    $offer = MerchantOffer::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchant->id,
        'price' => '32.500',
        'status' => OfferStatus::Submitted,
        'availability_status' => AvailabilityStatus::Available,
        'submitted_at' => now(),
    ]);

    return compact('merchant', 'owner', 'customerUser', 'customer', 'request', 'offer');
}

test('owning customer receives a whatsapp url from merchant business phone', function () {
    App::setLocale('en');
    ['merchant' => $merchant, 'owner' => $owner, 'customerUser' => $customerUser, 'customer' => $customer, 'request' => $request] = whatsappOfferSetup();

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestShowPage', false)
            ->has('offers', 1)
            ->where('offers.0.whatsapp_mobile_url', function ($url) use ($request, $customer) {
                $decoded = urldecode((string) $url);

                return str_starts_with((string) $url, 'https://wa.me/96891112222?text=')
                    && str_contains((string) $url, rawurlencode($request->public_id))
                    && str_contains($decoded, '32.500')
                    && str_contains($decoded, $request->public_id)
                    && str_contains($decoded, 'OMR')
                    && ! str_contains($decoded, '0100555666')
                    && ! str_contains($decoded, $customer->email)
                    && ! str_contains($decoded, (string) $customer->whatsapp_id)
                    && ! str_contains((string) $url, '999000111');
            })
            ->where('offers.0.whatsapp_web_url', function ($url) use ($request, $customer) {
                $decoded = urldecode((string) $url);

                return str_starts_with((string) $url, 'https://web.whatsapp.com/send?phone=96891112222&text=')
                    && str_contains((string) $url, rawurlencode($request->public_id))
                    && str_contains($decoded, '32.500')
                    && ! str_contains($decoded, '0100555666')
                    && ! str_contains($decoded, $customer->email)
                    && ! str_contains((string) $url, '999000111');
            })
            ->missing('offers.0.whatsapp_url')
            ->missing('offers.0.merchant')
            ->missing('offers.0.phone')
        );

    expect($owner->phone)->toBe('999000111')
        ->and($merchant->phone)->toBe('+968 9111-2222')
        ->and($customer->phone)->toBe('0100555666');
});

test('arabic locale prefills an arabic whatsapp message', function () {
    App::setLocale('ar');
    ['customerUser' => $customerUser, 'request' => $request] = whatsappOfferSetup();

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.whatsapp_mobile_url', function ($url) use ($request) {
                $decoded = urldecode((string) $url);

                return str_contains($decoded, 'مرحبًا')
                    && str_contains($decoded, $request->public_id)
                    && str_contains($decoded, '32.500');
            })
            ->where('offers.0.whatsapp_web_url', function ($url) use ($request) {
                $decoded = urldecode((string) $url);

                return str_starts_with((string) $url, 'https://web.whatsapp.com/send?phone=96891112222&text=')
                    && str_contains($decoded, 'مرحبًا')
                    && str_contains($decoded, $request->public_id);
            })
        );
});

test('another customer cannot obtain whatsapp contact for a foreign request', function () {
    ['request' => $request] = whatsappOfferSetup();
    $stranger = User::factory()->create();
    Customer::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->get(route('customer.requests.show', $request))
        ->assertNotFound();
});

test('withdrawn and invalidated offers do not expose a whatsapp url', function () {
    ['customerUser' => $customerUser, 'request' => $request, 'offer' => $offer] = whatsappOfferSetup();

    $offer->update(['status' => OfferStatus::Withdrawn, 'withdrawn_at' => now()]);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));

    $offer->update(['status' => OfferStatus::Invalidated, 'withdrawn_at' => null]);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));
});

test('missing or invalid merchant phone does not generate a whatsapp url', function () {
    ['customerUser' => $customerUser, 'request' => $request] = whatsappOfferSetup([
        'phone' => '01012345678',
    ]);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.whatsapp_mobile_url', null)
            ->where('offers.0.whatsapp_web_url', null)
        );

    ['customerUser' => $customerUser2, 'request' => $request2] = whatsappOfferSetup([
        'phone' => null,
    ]);

    $this->actingAs($customerUser2)
        ->get(route('customer.requests.show', $request2))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.whatsapp_mobile_url', null)
            ->where('offers.0.whatsapp_web_url', null)
        );
});

test('eight-digit oman merchant phone is prefixed for the customer whatsapp url', function () {
    ['customerUser' => $customerUser, 'request' => $request] = whatsappOfferSetup([
        'phone' => '91234567',
    ]);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.0.whatsapp_mobile_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96891234567?text='))
            ->where('offers.0.whatsapp_web_url', fn ($url) => str_starts_with((string) $url, 'https://web.whatsapp.com/send?phone=96891234567&text='))
        );
});
