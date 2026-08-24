<?php

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\Marketers\Status as MarketerStatus;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Marketer;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\CustomerContactAbuseService;
use App\Services\MerchantPermissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    app(MerchantPermissionService::class)->seedCatalog();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function abuseCustomer(array $userAttrs = []): array
{
    $user = User::factory()->create($userAttrs);
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ]);

    return compact('user', 'customer');
}

test('text contact abuse blocks persistence matching and suspends the customer', function (string $text) {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = abuseCustomer(['email' => 'text-abuse-'.md5($text).'@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => $text,
        ])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(RequestMatch::query()->count())->toBe(0)
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Suspended)
        ->and($customer->fresh()->suspension_reason)->toBe(CustomerContactAbuseService::REASON_CONTACT_INFORMATION)
        ->and($customer->fresh()->suspended_at)->not->toBeNull()
        ->and($user->fresh()->status)->toBe(UserStatus::Active);

    $log = ActivityLog::query()->where('subject_type', Customer::class)->where('subject_id', $customer->id)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['reason'] ?? null)->toBe('contact_information_in_request')
        ->and(json_encode($log->metadata))->not->toContain('968')
        ->and(json_encode($log->metadata))->not->toContain('@example.com');
})->with([
    'Call me on 9xxxxxxx',
    'WhatsApp 9xxxxxxx',
    'name@example.com',
    'https://example.com',
    'contact me on Instagram @partsoman',
]);

test('safe product text is not treated as contact abuse', function (string $text) {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = abuseCustomer(['email' => 'safe-'.md5($text).'@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => $text,
        ])
        ->assertOk();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active)
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(RequestMatch::query()->count())->toBe(0);
})->with([
    '10 pieces',
    'Toyota 2019',
    'Part No. 123456',
    '50 OMR budget',
    'size 120x80',
]);

test('mocked image contact abuse is blocked without live ai', function (string $token) {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = abuseCustomer(['email' => 'image-'.strtolower($token).'@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Need this part '.$token,
            'image' => UploadedFile::fake()->image('part.jpg'),
        ])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(RequestMatch::query()->count())->toBe(0)
        ->and($customer->fresh()->isSuspended())->toBeTrue();
})->with([
    'IMAGE_CONTACT_PHONE',
    'IMAGE_CONTACT_EMAIL',
    'IMAGE_CONTACT_URL',
    'IMAGE_CONTACT_QR',
]);

test('normal product images are allowed', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = abuseCustomer(['email' => 'normal-image@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
            'image' => UploadedFile::fake()->image('part.jpg'),
        ])
        ->assertOk();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active)
        ->and(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(RequestMatch::query()->count())->toBe(0);
});

test('forced qr contact token is blocked before matching', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = abuseCustomer(['email' => 'qr@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Need part FORCE_CONTACT_QR',
        ])
        ->assertSessionHasErrors('request_text');

    expect(CustomerRequest::query()->count())->toBe(0)
        ->and($customer->fresh()->suspension_types)->toContain('qr');
});

test('customer suspension does not disable merchant or marketer capability', function () {
    Category::factory()->create();
    $user = User::factory()->create(['email' => 'triple@example.com']);
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ]);
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->owner()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'status' => MembershipStatus::Active,
    ]);
    $marketer = Marketer::factory()->create([
        'user_id' => $user->id,
        'status' => MarketerStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Call me on 9xxxxxxx',
        ])
        ->assertSessionHasErrors('request_text');

    expect($customer->fresh()->isSuspended())->toBeTrue()
        ->and($user->fresh()->status)->toBe(UserStatus::Active)
        ->and($merchant->fresh()->isActive())->toBeTrue()
        ->and($marketer->fresh()->isActive())->toBeTrue();

    $this->actingAs($user)
        ->get(route('customer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customerContext.is_suspended', true)
        );

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor'])
        ->assertSessionHasErrors('request_text');

    $this->actingAs($user)
        ->post(route('merchant.context.store'), ['public_id' => $merchant->public_id])
        ->assertRedirect(route('merchant.home'));

    $this->actingAs($user)
        ->get(route('merchant.home'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('marketer.home'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.status_formatted.name', 'Suspended')
            ->where('customers.data.0.suspension_reason', 'contact_information_in_request')
        );

    $this->actingAs($this->admin)
        ->post(route('customers.reactivate', $customer))
        ->assertRedirect();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active)
        ->and($customer->fresh()->suspended_at)->toBeNull()
        ->and($customer->fresh()->suspension_reason)->toBe('contact_information_in_request');

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor'])
        ->assertOk();
});
