<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\Categories\Status;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\RequestMatchingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

function phase4Membership(User $user, Merchant $merchant, Role $role = Role::Staff, MembershipStatus $status = MembershipStatus::Active): MerchantUser
{
    return MerchantUser::factory()->create([
        'user_id' => $user->id,
        'merchant_id' => $merchant->id,
        'role' => $role,
        'status' => $status,
    ]);
}

function phase4AssignCategory(Merchant $merchant, Category $category): MerchantCategory
{
    return MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
}

function phase4Session(Merchant $merchant): array
{
    return [MerchantContextService::SESSION_KEY => $merchant->id];
}

test('request with category matches eligible active merchants', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    phase4AssignCategory($merchantA, $category);
    phase4AssignCategory($merchantB, $category);

    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
    ]);

    $result = app(RequestMatchingService::class)->sync($request);

    expect($result['created'])->toBe(2)
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(2)
        ->and(RequestMatch::query()->pluck('merchant_id')->sort()->values()->all())
        ->toEqual(collect([$merchantA->id, $merchantB->id])->sort()->values()->all());
});

test('matching uses exact category only and ignores parent-only assignment', function () {
    $parent = Category::factory()->create(['name_en' => 'Electronics']);
    $child = Category::factory()->create(['name_en' => 'Mobile Phones', 'parent_id' => $parent->id]);

    $exactMerchant = Merchant::factory()->create(['name' => 'Exact']);
    $parentMerchant = Merchant::factory()->create(['name' => 'Parent Only']);
    phase4AssignCategory($exactMerchant, $child);
    phase4AssignCategory($parentMerchant, $parent);

    $request = CustomerRequest::factory()->create([
        'category_id' => $child->id,
        'status' => RequestStatus::New,
    ]);

    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(RequestMatch::query()->where('merchant_id', $exactMerchant->id)->exists())->toBeTrue()
        ->and(RequestMatch::query()->where('merchant_id', $parentMerchant->id)->exists())->toBeFalse();
});

test('inactive merchant is not matched', function () {
    $category = Category::factory()->create();
    $active = Merchant::factory()->create();
    $inactive = Merchant::factory()->inactive()->create();
    phase4AssignCategory($active, $category);
    phase4AssignCategory($inactive, $category);

    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);

    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->where('merchant_id', $active->id)->exists())->toBeTrue()
        ->and(RequestMatch::query()->where('merchant_id', $inactive->id)->exists())->toBeFalse();
});

test('merchant without the request category is not matched', function () {
    $phones = Category::factory()->create();
    $parts = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4AssignCategory($merchant, $parts);

    $request = CustomerRequest::factory()->create(['category_id' => $phones->id]);

    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->count())->toBe(0);
});

test('request without category cannot be matched', function () {
    $request = CustomerRequest::factory()->create(['category_id' => null]);

    $this->actingAs($this->admin)
        ->post(route('customer-requests.match', $request))
        ->assertSessionHasErrors('category_id');

    expect(RequestMatch::query()->count())->toBe(0);
});

test('inactive category cannot be matched', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4AssignCategory($merchant, $category);

    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    $category->update(['status' => Status::Inactive]);

    $this->actingAs($this->admin)
        ->post(route('customer-requests.match', $request->fresh()))
        ->assertSessionHasErrors('category_id');

    expect(RequestMatch::query()->count())->toBe(0);
});

test('closed and cancelled requests are not matched', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4AssignCategory($merchant, $category);

    $closed = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Closed,
    ]);
    $cancelled = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'status' => RequestStatus::Cancelled,
    ]);

    $this->actingAs($this->admin)
        ->post(route('customer-requests.match', $closed))
        ->assertSessionHasErrors('category_id');

    $this->actingAs($this->admin)
        ->post(route('customer-requests.match', $cancelled))
        ->assertSessionHasErrors('category_id');

    expect(RequestMatch::query()->count())->toBe(0);
});

test('duplicate matching run creates no duplicates', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4AssignCategory($merchant, $category);
    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);

    $service = app(RequestMatchingService::class);
    $first = $service->sync($request);
    $second = $service->sync($request);

    expect($first['created'])->toBe(1)
        ->and($second['created'])->toBe(0)
        ->and($second['retained'])->toBe(1)
        ->and(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1);
});

test('same request can match multiple merchants', function () {
    $category = Category::factory()->create();
    $merchants = Merchant::factory()->count(3)->create();
    foreach ($merchants as $merchant) {
        phase4AssignCategory($merchant, $category);
    }

    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(3);
});

test('category change synchronizes matches and removes stale pending rows', function () {
    $phones = Category::factory()->create();
    $parts = Category::factory()->create();
    $phoneMerchant = Merchant::factory()->create(['name' => 'Phones']);
    $partsMerchant = Merchant::factory()->create(['name' => 'Parts']);
    phase4AssignCategory($phoneMerchant, $phones);
    phase4AssignCategory($partsMerchant, $parts);

    $customer = Customer::factory()->create();
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $phones->id,
        'status' => RequestStatus::New,
        'request_text' => 'Need a screen',
    ]);

    app(RequestMatchingService::class)->sync($request);
    expect(RequestMatch::query()->where('merchant_id', $phoneMerchant->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $request), [
            'customer_id' => $customer->public_id,
            'request_text' => $request->request_text,
            'status' => RequestStatus::New->value,
            'category_id' => $parts->public_id,
        ])
        ->assertRedirect();

    expect(RequestMatch::query()->where('customer_request_id', $request->id)->count())->toBe(1)
        ->and(RequestMatch::query()->where('merchant_id', $phoneMerchant->id)->exists())->toBeFalse()
        ->and(RequestMatch::query()->where('merchant_id', $partsMerchant->id)->exists())->toBeTrue();
});

test('viewed match for still-eligible merchant is preserved on rematch', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4AssignCategory($merchant, $category);
    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);

    app(RequestMatchingService::class)->sync($request);
    $match = RequestMatch::query()->first();
    $match->status = MatchStatus::Viewed;
    $match->save();

    app(RequestMatchingService::class)->sync($request->fresh());

    expect(RequestMatch::query()->count())->toBe(1)
        ->and($match->fresh()->status)->toBe(MatchStatus::Viewed);
});

test('merchant a sees only a matches and cannot see b-only requests', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    phase4AssignCategory($merchantA, $category);
    phase4AssignCategory($merchantB, $category);
    phase4Membership($this->user, $merchantA, Role::Owner);

    $requestA = CustomerRequest::factory()->create(['category_id' => $category->id, 'request_text' => 'Alpha only']);
    $requestB = CustomerRequest::factory()->create(['category_id' => $category->id, 'request_text' => 'Beta only']);

    RequestMatch::factory()->create([
        'customer_request_id' => $requestA->id,
        'merchant_id' => $merchantA->id,
    ]);
    RequestMatch::factory()->create([
        'customer_request_id' => $requestB->id,
        'merchant_id' => $merchantB->id,
    ]);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantRequestsPage', false)
            ->has('requests.data', 1)
            ->where('requests.data.0.public_id', $requestA->public_id)
            ->where('requests.data.0.request_text', 'Alpha only')
            ->missing('requests.data.0.customer'));

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.show', $requestB))
        ->assertForbidden();
});

test('forged request public id and forged merchant context do not grant access', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    phase4Membership($this->user, $merchantA, Role::Staff);
    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchantA->id,
    ]);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.show', ['customerRequest' => (string) Str::ulid()]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantB))
        ->get(route('merchant.requests.index'))
        ->assertRedirect(route('merchant.select'));

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.show', $request))
        ->assertOk();
});

test('knowing a request ulid is insufficient without a match row', function () {
    $request = CustomerRequest::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4Membership($this->user, $merchant);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->get(route('merchant.requests.show', $request))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->get(route('merchant.requests.image', $request))
        ->assertForbidden();
});

test('merchant can view matched request image and is denied for unmatched requests', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    phase4Membership($this->user, $merchantA);

    $requestA = CustomerRequest::factory()->create(['category_id' => $category->id]);
    $requestB = CustomerRequest::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $requestA), [
            'customer_id' => $requestA->customer->public_id,
            'request_text' => $requestA->request_text,
            'status' => $requestA->status->value,
            'image' => UploadedFile::fake()->image('a.jpg', 20, 20),
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $requestB), [
            'customer_id' => $requestB->customer->public_id,
            'request_text' => $requestB->request_text,
            'status' => $requestB->status->value,
            'image' => UploadedFile::fake()->image('b.jpg', 20, 20),
        ])
        ->assertRedirect();

    RequestMatch::factory()->create([
        'customer_request_id' => $requestA->id,
        'merchant_id' => $merchantA->id,
    ]);
    RequestMatch::factory()->create([
        'customer_request_id' => $requestB->id,
        'merchant_id' => $merchantB->id,
    ]);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.image', $requestA))
        ->assertOk();

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.image', $requestB))
        ->assertForbidden();

    $unmatched = CustomerRequest::factory()->create();
    $this->actingAs($this->admin)
        ->put(route('customer-requests.update', $unmatched), [
            'customer_id' => $unmatched->customer->public_id,
            'request_text' => $unmatched->request_text,
            'status' => $unmatched->status->value,
            'image' => UploadedFile::fake()->image('none.jpg', 20, 20),
        ])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.image', $unmatched))
        ->assertForbidden();
});

test('merchant response does not expose customer phone email or whatsapp id', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4Membership($this->user, $merchant, Role::Owner);

    $customer = Customer::factory()->create([
        'name' => 'Secret Customer',
        'phone' => '01099998888',
        'email' => 'secret-customer@example.test',
        'whatsapp_id' => 'wa-secret-999',
    ]);
    $request = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'request_text' => 'Need tires',
    ]);
    RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchant->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->get(route('merchant.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantRequestShowPage', false)
            ->where('request.public_id', $request->public_id)
            ->where('request.request_text', 'Need tires')
            ->missing('request.customer')
            ->missing('request.customer_id')
            ->missing('request.phone')
            ->missing('request.email')
            ->missing('request.whatsapp_id'));

    $content = $response->getContent();
    expect($content)->not->toContain('01099998888')
        ->and($content)->not->toContain('secret-customer@example.test')
        ->and($content)->not->toContain('wa-secret-999')
        ->and($content)->not->toContain('Secret Customer');
});

test('admin can trigger matching and view the global matching list', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create(['name' => 'Visible Shop']);
    phase4AssignCategory($merchant, $category);
    $request = CustomerRequest::factory()->create([
        'category_id' => $category->id,
        'request_text' => 'Need a battery',
    ]);

    $this->actingAs($this->admin)
        ->post(route('customer-requests.match', $request), [
            'merchant_id' => 999999,
            'customer_request_id' => 999999,
            'status' => MatchStatus::Viewed->value,
        ])
        ->assertRedirect();

    expect(RequestMatch::query()->count())->toBe(1)
        ->and(RequestMatch::query()->first()->status)->toBe(MatchStatus::Pending)
        ->and(RequestMatch::query()->first()->merchant_id)->toBe($merchant->id);

    $this->actingAs($this->admin)
        ->get(route('matching.index', ['search' => 'Visible Shop', 'status' => MatchStatus::Pending->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Matching/MatchingPage', false)
            ->has('matches.data', 1)
            ->where('matches.data.0.merchant.name', 'Visible Shop'));
});

test('merchant cannot create match rows or access admin matching', function () {
    $merchant = Merchant::factory()->create();
    phase4Membership($this->user, $merchant);
    $request = CustomerRequest::factory()->create();

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->get(route('matching.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->post(route('customer-requests.match', $request))
        ->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->get(route('customer-requests.index'))
        ->assertRedirect(route('login'));
});

test('opening a matched request marks pending as viewed and dismiss hides only own match', function () {
    $category = Category::factory()->create();
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $userB = User::factory()->create();
    phase4Membership($this->user, $merchantA, Role::Owner);
    phase4Membership($userB, $merchantB, Role::Owner);

    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    $matchA = RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchantA->id,
        'status' => MatchStatus::Pending,
    ]);
    $matchB = RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchantB->id,
        'status' => MatchStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.show', $request))
        ->assertOk();

    expect($matchA->fresh()->status)->toBe(MatchStatus::Viewed)
        ->and($matchB->fresh()->status)->toBe(MatchStatus::Pending);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->post(route('merchant.requests.dismiss', $request))
        ->assertRedirect(route('merchant.requests.index'));

    expect($matchA->fresh()->status)->toBe(MatchStatus::Dismissed)
        ->and($matchB->fresh()->status)->toBe(MatchStatus::Pending);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchantA))
        ->get(route('merchant.requests.show', $request))
        ->assertForbidden();
});

test('inactive membership cannot use merchant request routes', function () {
    $merchant = Merchant::factory()->create();
    phase4Membership($this->user, $merchant, Role::Staff, MembershipStatus::Inactive);
    $request = CustomerRequest::factory()->create();
    RequestMatch::factory()->create([
        'customer_request_id' => $request->id,
        'merchant_id' => $merchant->id,
    ]);

    $this->actingAs($this->user)
        ->withSession(phase4Session($merchant))
        ->get(route('merchant.requests.index'))
        ->assertRedirect(route('merchant.select'));
});

test('matching summary activity log is one record rather than one per merchant', function () {
    $category = Category::factory()->create();
    Merchant::factory()->count(3)->create()->each(fn (Merchant $merchant) => phase4AssignCategory($merchant, $category));
    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);

    $before = ActivityLog::query()->count();
    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->count())->toBe(3)
        ->and(ActivityLog::query()->count())->toBe($before + 1)
        ->and(ActivityLog::query()->where('event', Event::Updated)->where('subject_id', $request->id)->latest('id')->first()?->metadata)
        ->toMatchArray(['action' => 'matching.sync', 'created' => 3, 'removed' => 0, 'retained' => 0, 'eligible' => true]);
});

test('creating a request with a category auto-matches eligible merchants', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    phase4AssignCategory($merchant, $category);
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('customer-requests.store'), [
            'customer_id' => $customer->public_id,
            'request_text' => 'Auto match please',
            'status' => RequestStatus::New->value,
            'category_id' => $category->public_id,
        ])
        ->assertRedirect();

    $request = CustomerRequest::query()->first();
    expect(RequestMatch::query()->where('customer_request_id', $request->id)->where('merchant_id', $merchant->id)->exists())->toBeTrue();
});

test('detaching a merchant category removes stale matches for that category', function () {
    $category = Category::factory()->create();
    $merchant = Merchant::factory()->create();
    $assignment = phase4AssignCategory($merchant, $category);
    $request = CustomerRequest::factory()->create(['category_id' => $category->id]);
    app(RequestMatchingService::class)->sync($request);

    expect(RequestMatch::query()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->delete(route('merchants.categories.destroy', [$merchant, $assignment]))
        ->assertRedirect();

    expect(RequestMatch::query()->count())->toBe(0);
});
