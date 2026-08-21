<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\RequestImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

test('admin can create a customer with unique public_id', function () {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'Sara Ahmed',
            'phone' => '01011112222',
            'email' => 'sara@example.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('email', 'sara@example.test')->first();

    expect($customer)->not->toBeNull()
        ->and(Str::isUlid($customer->public_id))->toBeTrue()
        ->and($customer->user_id)->not->toBeNull()
        ->and(ActivityLog::where('event', Event::Created)->where('subject_id', $customer->id)->exists())->toBeTrue();
});

test('admin can update customer including inactive status', function () {
    $customer = Customer::factory()->create(['name' => 'Before']);

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), [
            'name' => 'After',
            'phone' => $customer->phone,
            'email' => $customer->email,
            'status' => CustomerStatus::Inactive->value,
        ])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('After')
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Inactive);
});

test('unauthorized user cannot manage customers', function () {
    $this->actingAs($this->user)
        ->get(route('customers.index'))
        ->assertRedirect(route('login'));
});

test('admin can create a request without merchant ownership', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'Need a Samsung screen',
            'status' => RequestStatus::New->value,
            'source' => Source::WhatsApp->value,
            'public_id' => (string) Str::ulid(),
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and(Str::isUlid($request->public_id))->toBeTrue()
        ->and($request->source)->toBe(Source::Admin)
        ->and($request->category_id)->toBeNull()
        ->and(Schema::hasColumn('customer_requests', 'merchant_id'))->toBeFalse();
});

test('active category can be assigned and inactive category is rejected', function () {
    $customer = Customer::factory()->create();
    $active = Category::factory()->create();
    $inactive = Category::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'With category',
            'status' => RequestStatus::Ready->value,
            'category_id' => $active->public_id,
        ])
        ->assertRedirect();

    expect(CustomerRequest::query()->first()->category_id)->toBe($active->id);

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'Inactive category',
            'status' => RequestStatus::New->value,
            'category_id' => $inactive->public_id,
        ])
        ->assertSessionHasErrors('category_id');
});

test('request status can be updated', function () {
    $request = CustomerRequest::factory()->create(['status' => RequestStatus::New]);

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $request->customer->public_id,
            'request_text' => $request->request_text,
            'status' => RequestStatus::Closed->value,
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(RequestStatus::Closed);
});

test('inactive customer cannot receive new requests', function () {
    $customer = Customer::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'Should fail',
            'status' => RequestStatus::New->value,
        ])
        ->assertSessionHasErrors('customer_id');
});

test('request may have zero images or one valid image', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'No image',
            'status' => RequestStatus::New->value,
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->first();
    expect($request->image)->toBeNull();

    $file = UploadedFile::fake()->image('need.jpg', 20, 20);

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'With image',
            'status' => RequestStatus::New->value,
            'image' => $file,
        ])
        ->assertRedirect();

    $withImage = CustomerRequest::query()->latest('id')->first();
    expect($withImage->image)->not->toBeNull();
    Storage::disk('local')->assertExists($withImage->image->path);
});

test('second image replaces the first and old file is deleted', function () {
    $request = CustomerRequest::factory()->create();
    $first = UploadedFile::fake()->image('first.jpg', 20, 20);

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $request->customer->public_id,
            'request_text' => $request->request_text,
            'status' => $request->status->value,
            'image' => $first,
        ])
        ->assertRedirect();

    $oldPath = $request->fresh()->image->path;

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $request->customer->public_id,
            'request_text' => $request->request_text,
            'status' => $request->status->value,
            'image' => UploadedFile::fake()->image('second.png', 20, 20),
        ])
        ->assertRedirect();

    $request->refresh()->load('image');

    expect(RequestImage::query()->where('customer_request_id', $request->id)->count())->toBe(1);
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($request->image->path);
});

test('invalid and unsafe images are rejected', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'bad file',
            'status' => RequestStatus::New->value,
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('image');

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'svg',
            'status' => RequestStatus::New->value,
            'image' => UploadedFile::fake()->create('x.svg', 20, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('image');
});

test('image is not publicly exposed and requires admin authorization', function () {
    $request = CustomerRequest::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $request->customer->public_id,
            'request_text' => $request->request_text,
            'status' => $request->status->value,
            'image' => UploadedFile::fake()->image('private.jpg', 20, 20),
        ])
        ->assertRedirect();

    $path = $request->fresh()->image->path;

    $this->get('/storage/'.$path)->assertStatus(403);

    $this->actingAs($this->admin)
        ->get(route('customer-requests.image', $request))
        ->assertOk();

    $this->actingAs($this->user)
        ->get(route('customer-requests.image', $request))
        ->assertRedirect(route('login'));
});

test('merchant user cannot access customer requests', function () {
    $merchant = Merchant::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $this->user->id,
        'merchant_id' => $merchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($this->user)
        ->get(route('customer-requests.index'))
        ->assertRedirect(route('login'));
});

test('forged customer and request public ids fail safely', function () {
    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => '01FAKECUSTOMERPUBLICID0000',
            'request_text' => 'Nope',
            'status' => RequestStatus::New->value,
        ])
        ->assertSessionHasErrors('customer_id');

    $this->actingAs($this->admin)
        ->get(route('customer-requests.image', ['customerRequest' => '01FAKEREQUESTPUBLICID00000']))
        ->assertNotFound();
});
