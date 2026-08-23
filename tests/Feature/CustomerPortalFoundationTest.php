<?php

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MerchantContextService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function linkedCustomer(?array $userAttrs = [], ?array $customerAttrs = []): array
{
    $user = User::factory()->create($userAttrs);
    $customer = Customer::factory()->create(array_merge([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ], $customerAttrs));

    return compact('user', 'customer');
}

test('customer public registration is unified and does not create a customer', function () {
    $this->post(route('customer.register.store'), [
        'name' => 'Portal Customer',
        'email' => 'portal-customer@example.com',
        'phone' => '0100111222',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('account.get-started'));

    $user = User::query()->where('email', 'portal-customer@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('password12', $user->password))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and($user->customer)->toBeNull()
        ->and($user->merchantMemberships()->count())->toBe(0);
});

test('duplicate user email is rejected for customer registration', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('customer.register.store'), [
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertSessionHasErrors('email');

    expect(Customer::query()->where('email', 'taken@example.com')->count())->toBe(0);
});

test('legacy customer registration redirects to unified register', function () {
    $this->get(route('customer.register'))->assertRedirect(route('register'));
    $this->get('/register')->assertOk();
});

test('linked customer can access portal while guest and unlinked user cannot', function () {
    ['user' => $user] = linkedCustomer();
    $plain = User::factory()->create();

    $this->get(route('customer.home'))->assertRedirect(route('login'));

    $this->actingAs($plain)
        ->get(route('customer.home'))
        ->assertRedirect(route('account.customer.enable'));

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('CustomerPortal/HomePage', false));
});

test('customer can create web request that becomes ready and matches eligible merchants', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $otherCategory = Category::factory()->create(['status' => CategoryStatus::Active]);
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchantA->id, 'category_id' => $category->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchantB->id, 'category_id' => $otherCategory->id]);

    ['user' => $user, 'customer' => $customer] = linkedCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'Need plumbing help',
            'image' => UploadedFile::fake()->image('need.jpg'),
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($request)->not->toBeNull()
        ->and($request->source)->toBe(Source::Web)
        ->and($request->status)->toBe(RequestStatus::Ready)
        ->and($request->category_id)->toBe($category->id)
        ->and($request->image)->not->toBeNull()
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->value('merchant_id'))->toBe($merchantA->id);

    // Forged ownership/source fields must not be accepted.
    $this->actingAs($user)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'Forged attempt',
            'customer_id' => Customer::factory()->create()->public_id,
            'source' => Source::WhatsApp->value,
            'status' => RequestStatus::New->value,
        ])
        ->assertSessionHasErrors(['customer_id', 'source', 'status']);
});

test('inactive category is rejected for customer request creation', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Inactive]);
    ['user' => $user] = linkedCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'Should fail',
        ])
        ->assertSessionHasErrors('category_id');
});

test('customer ownership isolation for requests and images', function () {
    ['user' => $userA, 'customer' => $customerA] = linkedCustomer(['email' => 'a@example.com']);
    ['user' => $userB] = linkedCustomer(['email' => 'b@example.com']);
    $category = Category::factory()->create();

    $request = CustomerRequest::factory()->create([
        'customer_id' => $customerA->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'source' => Source::Web,
    ]);

    $this->actingAs($userA)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'with image',
            'image' => UploadedFile::fake()->image('own.jpg'),
        ]);

    $withImage = CustomerRequest::query()->where('customer_id', $customerA->id)->where('request_text', 'with image')->first();

    $this->actingAs($userA)
        ->get(route('customer.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestsIndexPage', false)
            ->has('requests.data')
        );

    $this->actingAs($userA)
        ->get(route('customer.requests.show', $request))
        ->assertOk();

    $this->actingAs($userB)
        ->get(route('customer.requests.show', $request))
        ->assertNotFound();

    $this->actingAs($userB)
        ->get(route('customer.requests.image', $withImage))
        ->assertNotFound();

    $this->actingAs($userA)
        ->get(route('customer.requests.image', $withImage))
        ->assertOk();
});

test('customer cannot access admin dashboard modules', function () {
    ['user' => $user] = linkedCustomer();

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('merchants.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('matching.index'))
        ->assertRedirect(route('login'));
});

test('customer only user is redirected from dashboard to customer portal', function () {
    ['user' => $user] = linkedCustomer();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('customer.home'));
});

test('admin customer and request management still works', function () {
    $customer = Customer::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'category_id' => $category->public_id,
            'request_text' => 'Admin created',
            'status' => RequestStatus::Ready->value,
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('request_text', 'Admin created')->first();

    expect($request)->not->toBeNull()
        ->and($request->source)->toBe(Source::Admin);

    $this->actingAs($this->admin)
        ->get(route('customer-requests.image', $request->fresh(['image']) ?: $request))
        ->assertNotFound(); // no image uploaded

    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertOk();
});

test('merchant matched image access still works for customer-created request', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    $merchantUser = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $merchantUser->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    ['user' => $customerUser] = linkedCustomer();

    $this->actingAs($customerUser)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'Merchant visible',
            'image' => UploadedFile::fake()->image('shared.jpg'),
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->where('request_text', 'Merchant visible')->first();

    $this->actingAs($merchantUser)
        ->withSession([MerchantContextService::SESSION_KEY => $merchant->id])
        ->get(route('merchant.requests.image', $request))
        ->assertOk();
});

test('forged customer_id and source are rejected on portal create', function () {
    ['user' => $user, 'customer' => $customer] = linkedCustomer();
    $category = Category::factory()->create();

    $this->actingAs($user)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'Forged fields',
            'customer_id' => (string) Str::ulid(),
            'source' => 'whatsapp',
        ])
        ->assertSessionHasErrors(['customer_id', 'source']);

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->where('request_text', 'Forged fields')->count())->toBe(0);
});
