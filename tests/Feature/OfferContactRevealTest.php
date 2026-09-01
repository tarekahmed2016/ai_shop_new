<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOfferContactReveal;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Services\OfferContactRevealService;
use App\Services\RequestMatchingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
});

function contactRevealMerchant(Category $category, string $phone, string $name): Merchant
{
    $merchant = Merchant::factory()->create([
        'phone' => $phone,
        'name' => $name,
        'email' => Str::slug($name).'@merchant.test',
    ]);
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

    return $merchant;
}

function contactRevealSetup(int $merchantCount = 4): array
{
    $category = Category::factory()->create();
    $customerUser = User::factory()->create(['phone' => '0100555666']);
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

    $phones = ['91111111', '92222222', '93333333', '94444444', '95555555'];
    $merchants = [];
    $offers = [];

    for ($i = 0; $i < $merchantCount; $i++) {
        $merchant = contactRevealMerchant($category, $phones[$i], 'Shop '.($i + 1));
        $merchants[] = $merchant;
        app(RequestMatchingService::class)->sync($request);
        $offers[] = MerchantOffer::factory()->create([
            'customer_request_id' => $request->id,
            'merchant_id' => $merchant->id,
            'price' => number_format(10 + $i, 3, '.', ''),
            'status' => OfferStatus::Submitted,
            'availability_status' => AvailabilityStatus::Available,
            'submitted_at' => now()->subMinutes($i),
        ]);
    }

    return compact('category', 'customerUser', 'customer', 'request', 'merchants', 'offers');
}

test('customer sees offers without merchant contact information before reveal', function () {
    ['customerUser' => $customerUser, 'request' => $request, 'merchants' => $merchants] = contactRevealSetup(1);

    $content = $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestShowPage', false)
            ->has('offers', 1)
            ->where('offers.0.merchant_name', 'Shop 1')
            ->where('offers.0.contact_revealed', false)
            ->where('offers.0.contact', null)
            ->where('contactReveal.limit', 3)
            ->where('contactReveal.used', 0)
            ->where('contactReveal.remaining', 3)
            ->missing('offers.0.merchant')
            ->missing('offers.0.phone')
            ->missing('offers.0.email')
            ->missing('offers.0.whatsapp_mobile_url')
            ->missing('offers.0.whatsapp_web_url')
        )
        ->getContent();

    expect($content)->not->toContain('91111111')
        ->and($content)->not->toContain('wa.me/')
        ->and($content)->not->toContain($merchants[0]->email);
});

test('customer can reveal up to three distinct merchants and reload keeps contact', function () {
    App::setLocale('en');
    ['customerUser' => $customerUser, 'request' => $request, 'offers' => $offers] = contactRevealSetup(4);

    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();
    revealCustomerOfferContact($customerUser, $offers[1])->assertRedirect();
    revealCustomerOfferContact($customerUser, $offers[2])->assertRedirect();

    expect(CustomerOfferContactReveal::query()->count())->toBe(3);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 4)
            ->where('contactReveal.used', 3)
            ->where('contactReveal.remaining', 0)
            ->where('offers.0.contact_revealed', true)
            ->where('offers.1.contact_revealed', true)
            ->where('offers.2.contact_revealed', true)
            ->where('offers.3.contact_revealed', false)
            ->where('offers.3.contact', null)
            ->where('offers.0.contact.whatsapp_mobile_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96891111111?text='))
            ->where('offers.1.contact.whatsapp_mobile_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96892222222?text='))
            ->where('offers.2.contact.whatsapp_mobile_url', fn ($url) => str_starts_with((string) $url, 'https://wa.me/96893333333?text='))
        );
});

test('fourth distinct merchant reveal is blocked without hiding offers', function () {
    App::setLocale('en');
    ['customerUser' => $customerUser, 'request' => $request, 'offers' => $offers] = contactRevealSetup(4);

    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();
    revealCustomerOfferContact($customerUser, $offers[1])->assertRedirect();
    revealCustomerOfferContact($customerUser, $offers[2])->assertRedirect();

    revealCustomerOfferContact($customerUser, $offers[3])
        ->assertSessionHasErrors('offer');

    expect(CustomerOfferContactReveal::query()->count())->toBe(3);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 4)
            ->where('offers.3.contact_revealed', false)
            ->where('offers.3.contact', null)
            ->where('offers.3.merchant_name', 'Shop 4')
        );
});

test('revealing the same merchant repeatedly does not consume extra slots', function () {
    ['customerUser' => $customerUser, 'request' => $request, 'offers' => $offers] = contactRevealSetup(2);

    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();
    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();
    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();

    expect(CustomerOfferContactReveal::query()->count())->toBe(1)
        ->and(app(OfferContactRevealService::class)->quotaSnapshot($request, $request->customer)['used'])->toBe(1)
        ->and(app(OfferContactRevealService::class)->quotaSnapshot($request, $request->customer)['remaining'])->toBe(2);
});

test('customer cannot reveal an offer from another customers request', function () {
    ['offers' => $offers] = contactRevealSetup(1);
    $stranger = User::factory()->create();
    Customer::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->post(route('customer.offers.contact-reveal', $offers[0]))
        ->assertForbidden();

    expect(CustomerOfferContactReveal::query()->count())->toBe(0);
});

test('withdrawn and invalidated offers cannot be revealed', function () {
    ['customerUser' => $customerUser, 'request' => $request, 'offers' => $offers] = contactRevealSetup(1);

    $offers[0]->update(['status' => OfferStatus::Withdrawn, 'withdrawn_at' => now()]);

    $this->actingAs($customerUser)
        ->post(route('customer.offers.contact-reveal', $offers[0]))
        ->assertForbidden();

    $offers[0]->update(['status' => OfferStatus::Invalidated, 'withdrawn_at' => null]);

    $this->actingAs($customerUser)
        ->post(route('customer.offers.contact-reveal', $offers[0]))
        ->assertForbidden();

    expect(CustomerOfferContactReveal::query()->count())->toBe(0);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('offers', 0));
});

test('tampered offer ids and merchant-only users are rejected', function () {
    ['customerUser' => $customerUser, 'offers' => $offers, 'merchants' => $merchants] = contactRevealSetup(1);

    $this->actingAs($customerUser)
        ->post(route('customer.offers.contact-reveal', '01hzzzzzzzzzzzzzzzzzzzzzzz'))
        ->assertNotFound();

    $merchantOwner = $merchants[0]->memberships()->first()->user;
    $this->actingAs($merchantOwner)
        ->post(route('customer.offers.contact-reveal', $offers[0]))
        ->assertRedirect(route('account.customer.enable'));

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)
        ->post(route('customer.offers.contact-reveal', $offers[0]))
        ->assertRedirect(route('dashboard'));

    expect(CustomerOfferContactReveal::query()->count())->toBe(0);
});

test('concurrent distinct reveals cannot exceed the configured limit', function () {
    ['customerUser' => $customerUser, 'customer' => $customer, 'offers' => $offers] = contactRevealSetup(4);
    $service = app(OfferContactRevealService::class);

    $service->reveal($customer, $offers[0]);
    $service->reveal($customer, $offers[1]);
    $service->reveal($customer, $offers[2]);

    expect(fn () => $service->reveal($customer, $offers[3]))->toThrow(ValidationException::class);
    expect(fn () => $service->reveal($customer, $offers[3]))->toThrow(ValidationException::class);

    expect(CustomerOfferContactReveal::query()->count())->toBe(3);

    $source = file_get_contents(app_path('Services/OfferContactRevealService.php'));
    expect($source)->toContain('lockForUpdate')
        ->and($source)->toContain('UniqueConstraintViolationException');

    revealCustomerOfferContact($customerUser, $offers[2])->assertRedirect();
    expect(CustomerOfferContactReveal::query()->count())->toBe(3);
});

test('customer inertia props do not leak a raw merchant model', function () {
    ['customerUser' => $customerUser, 'request' => $request, 'merchants' => $merchants, 'offers' => $offers] = contactRevealSetup(1);

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('offers.0.merchant')
            ->missing('offers.0.merchant.phone')
            ->missing('offers.0.merchant.email')
            ->missing('offers.0.phone')
            ->missing('offers.0.email')
            ->missing('offers.0.contact.email')
            ->where('offers.0.contact', null)
            ->where('offers.0.contact_revealed', false)
        );

    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();

    $this->actingAs($customerUser)
        ->get(route('customer.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('offers.0.merchant')
            ->missing('offers.0.contact.email')
            ->where('offers.0.contact.phone', $merchants[0]->phone)
            ->where('offers.0.contact.merchant_name', 'Shop 1')
        );
});

test('merchant offer credit and matching behavior is unchanged by contact reveal', function () {
    ['customerUser' => $customerUser, 'request' => $request, 'offers' => $offers, 'merchants' => $merchants] = contactRevealSetup(1);

    expect(RequestMatchingService::class)->toBeString();

    revealCustomerOfferContact($customerUser, $offers[0])->assertRedirect();

    expect($offers[0]->fresh()->status)->toBe(OfferStatus::Submitted)
        ->and($merchants[0]->fresh()->merchantOffers()->count())->toBe(1)
        ->and($request->fresh()->submittedOffers()->count())->toBe(1);
});
