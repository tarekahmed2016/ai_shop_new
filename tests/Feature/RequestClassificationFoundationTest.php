<?php

use App\Contracts\AiClassificationProviderInterface;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Status;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\RequestClassification;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\Classification\FakeClassificationProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * QUEUE_CONNECTION=sync in phpunit.xml means every ClassifyCustomerRequestJob
 * / DetectDuplicateCustomerRequestJob / FinalizeCustomerRequestJob dispatch
 * in this file runs synchronously, inline, before the triggering HTTP
 * response is returned. That lets these tests assert on the pipeline's
 * *end* state after one HTTP call, exactly like the old synchronous
 * implementation did — while the actual production code path never calls
 * an AI provider from the HTTP request (see AsyncClassificationPipelineTest
 * for tests that freeze the pipeline mid-flight with Queue::fake()).
 */
beforeEach(function () {
    Storage::fake('local');
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    enableAsyncClassification();
});

function classificationCustomer(?array $userAttrs = []): array
{
    $user = User::factory()->create(array_merge([
        'email' => 'classify-user@example.com',
        'phone' => '0100111222',
        'name' => 'Classify User',
    ], $userAttrs));
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'whatsapp_id' => 'wa-secret-'.($userAttrs['email'] ?? $user->email),
        'status' => CustomerStatus::Active,
    ]);

    return compact('user', 'customer');
}

function submissionToken(): string
{
    return (string) Str::ulid();
}

test('guest and unlinked user cannot classify', function () {
    $this->post(route('customer.requests.classify'), [
        'request_text' => 'Need ABS sensor FORCE_HIGH',
        'submission_token' => submissionToken(),
    ])->assertRedirect(route('login'));

    $plain = User::factory()->create();
    $this->actingAs($plain)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Need ABS sensor',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect(route('account.customer.enable'));
});

test('customer submitted category_id is prohibited and cannot skip ai classification', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    ['user' => $user, 'customer' => $customer] = classificationCustomer();

    $this->actingAs($user)
        ->get(route('customer.requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestCreatePage', false)
            ->missing('availableCategories')
        );

    $this->actingAs($user)
        ->post(route('customer.requests.store'), [
            'category_id' => $category->public_id,
            'request_text' => 'Need plumbing help',
        ])
        ->assertSessionHasErrors('category_id');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(RequestClassification::query()->count())->toBe(0)
        ->and(RequestMatch::query()->count())->toBe(0)
        ->and(file_get_contents(resource_path('js/Pages/CustomerPortal/RequestCreatePage.vue')))->not->toContain('CategoryTreeSelector')
        ->and(file_get_contents(resource_path('js/Pages/CustomerPortal/RequestShowPage.vue')))->not->toContain('CategoryTreeSelector');
});

test('classify validates required fields and never calls AI without a valid submission', function () {
    ['user' => $user, 'customer' => $customer] = classificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
            'submission_token' => submissionToken(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'merchant_id' => 999,
        ])
        ->assertSessionHasErrors(['customer_id', 'user_id', 'merchant_id']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
        ])
        ->assertSessionHasErrors('submission_token');

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(0);
});

test('high confidence suggestion does not match until customer confirms', function () {
    $parent = Category::factory()->create(['name_en' => 'Vehicles', 'status' => CategoryStatus::Active]);
    $category = Category::factory()->create([
        'name_en' => 'Auto Spare Parts',
        'parent_id' => $parent->id,
        'status' => CategoryStatus::Active,
    ]);
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    ['user' => $user, 'customer' => $customer] = classificationCustomer();

    $response = $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
            'submission_token' => submissionToken(),
            'image' => UploadedFile::fake()->image('part.jpg'),
        ]);

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $response->assertRedirect(route('customer.requests.show', $pending));

    $attempt = RequestClassification::query()->where('customer_request_id', $pending->id)->first();

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification)
        ->and($pending->fresh()->ai_stage)->toBe(AiStage::ReadyForReview)
        ->and($pending->fresh()->category_id)->toBeNull()
        ->and($pending->fresh()->quota_consumed_at)->toBeNull()
        ->and(RequestMatch::query()->where('customer_request_id', $pending->id)->count())->toBe(0)
        ->and($attempt->status)->toBe(ClassificationStatus::Suggested)
        ->and($attempt->suggested_category_id)->toBe($category->id)
        ->and($attempt->provider)->toBe('fake')
        ->and($attempt->model)->toBe('fake-v1')
        ->and($attempt->input_has_image)->toBeTrue()
        ->and($attempt->confidence)->toBe(0.9);

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestShowPage', false)
            ->where('classification.confidence_band', 'high')
            ->where('classification.detected_item', 'ABS Sensor')
            ->where('classification.primary.category_public_id', $category->public_id)
            ->where('classification.failed', false)
        );

    $provider = app(AiClassificationProviderInterface::class);
    expect($provider)->toBeInstanceOf(FakeClassificationProvider::class)
        ->and($provider->lastInput?->hasImage)->toBeTrue()
        ->and($provider->lastInput?->imageContents)->not->toBeEmpty();

    $encoded = json_encode($provider->lastInput?->auditSnapshot());
    expect($encoded)->not->toContain('classify-user@example.com')
        ->and($encoded)->not->toContain('0100111222')
        ->and($encoded)->not->toContain('wa-secret-')
        ->and($encoded)->not->toContain($customer->public_id)
        ->and($encoded)->not->toContain($user->email)
        ->and($encoded)->not->toContain($customer->name);

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $attempt), [
            'category_id' => $category->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect(route('customer.requests.show', $pending));

    expect($pending->fresh()->status)->toBe(RequestStatus::Ready)
        ->and($pending->fresh()->ai_stage)->toBe(AiStage::Ready)
        ->and($pending->fresh()->category_id)->toBe($category->id)
        ->and($pending->fresh()->quota_consumed_at)->not->toBeNull()
        ->and($attempt->fresh()->status)->toBe(ClassificationStatus::Confirmed)
        ->and($attempt->fresh()->customer_confirmed_category_id)->toBe($category->id)
        ->and(RequestMatch::query()->where('customer_request_id', $pending->id)->count())->toBe(1);
});

test('medium confidence presents up to three suggestions and confirms selected one', function () {
    $a = Category::factory()->create(['name_en' => 'Auto Spare Parts']);
    $b = Category::factory()->create(['name_en' => 'Auto Electrical']);
    Category::factory()->create(['name_en' => 'Car Accessories']);
    ['user' => $user] = classificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'FORCE_MEDIUM electrical part',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    $classification = RequestClassification::query()->latest('id')->first();
    expect(count($classification->alternatives ?? []))->toBeLessThanOrEqual(3)
        ->and($classification->customerRequest->ai_stage)->toBe(AiStage::ReadyForReview);

    $this->actingAs($user)
        ->get(route('customer.requests.show', $classification->customerRequest))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classification.confidence_band', 'medium')
            ->has('classification.suggestions')
        );

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $b->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    expect($classification->fresh()->suggested_category_id)->toBe($a->id)
        ->and($classification->fresh()->customer_confirmed_category_id)->toBe($b->id)
        ->and($classification->customerRequest->fresh()->category_id)->toBe($b->id);
});

test('low confidence shows needs more information and retry creates history', function () {
    Category::factory()->create();
    ['user' => $user] = classificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'FORCE_LOW unknown part',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    $pending = CustomerRequest::query()->first();

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classification.confidence_band', 'low')
            ->where('classification.needs_more_information', true)
            ->where('classification.question', 'What vehicle make and model is this part for?')
        );

    expect(RequestMatch::query()->count())->toBe(0);

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'Toyota Camry 2018',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect(route('customer.requests.show', $pending));

    expect(RequestClassification::query()->count())->toBe(2)
        ->and($pending->fresh()->request_text)->toContain('Toyota Camry 2018');
});

test('customer cannot classify or confirm another customers request', function () {
    $category = Category::factory()->create();
    ['user' => $userA] = classificationCustomer(['email' => 'a-class@example.com']);
    ['user' => $userB] = classificationCustomer(['email' => 'b-class@example.com']);

    $this->actingAs($userA)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => submissionToken()]);

    $classification = RequestClassification::query()->first();
    $pending = $classification->customerRequest;

    $this->actingAs($userB)
        ->get(route('customer.requests.show', $pending))
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'more',
            'submission_token' => submissionToken(),
        ])
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $category->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertNotFound();

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification);
});

test('inactive and invented categories from provider are ignored and cannot be confirmed', function () {
    $active = Category::factory()->create(['status' => CategoryStatus::Active]);
    $inactive = Category::factory()->create(['status' => CategoryStatus::Inactive]);
    ['user' => $user] = classificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'FORCE_MALFORMED mystery',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    $first = RequestClassification::query()->latest('id')->first();
    $this->actingAs($user)
        ->get(route('customer.requests.show', $first->customerRequest))
        ->assertInertia(fn (Assert $page) => $page->where('classification.primary', null));

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $first->customerRequest), [
            'additional_details' => 'FORCE_INACTIVE '.$inactive->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    $classification = RequestClassification::query()->latest('id')->first();
    expect($classification->suggested_category_id)->toBeNull();

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $inactive->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertSessionHasErrors('category_id');

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $active->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertSessionHasErrors('category_id');
});

test('provider failure stays friendly and retry remains available', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = classificationCustomer();

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'FORCE_FAIL boom',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    expect($pending->ai_stage)->toBe(AiStage::Failed)
        ->and(RequestClassification::query()->latest('id')->first()->status)->toBe(ClassificationStatus::Failed)
        ->and(RequestMatch::query()->count())->toBe(0)
        ->and($pending->quota_consumed_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertInertia(fn (Assert $page) => $page->where('classification.failed', true));

    // The stored request_text is appended-to, not replaced (same mechanism
    // used for "needs more information" clarifications), so the original
    // FORCE_FAIL trigger is still present and the provider fails again —
    // this proves retry creates a fresh attempt/history row and the row
    // stays retryable (never gets stuck) even across repeated failures.
    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'still no luck',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(RequestClassification::query()->where('customer_request_id', $pending->id)->count())->toBe(2)
        ->and($pending->fresh()->ai_stage)->toBe(AiStage::Failed);

    // A retry with text that no longer contains the trigger word succeeds.
    $secondPending = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => Status::PendingClassification,
        'ai_stage' => AiStage::Failed,
        'ai_stage_updated_at' => now(),
        'request_text' => 'a broken sensor',
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $secondPending), [
            'additional_details' => 'ABS Sensor for my car',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    expect($secondPending->fresh()->ai_stage)->toBe(AiStage::ReadyForReview);
});

test('pending classification can be resumed from request details after leaving create page', function () {
    $category = Category::factory()->create(['name_en' => 'Auto Spare Parts', 'status' => CategoryStatus::Active]);
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    ['user' => $user, 'customer' => $customer] = classificationCustomer(['email' => 'resume-later@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor for my car',
            'submission_token' => submissionToken(),
            'image' => UploadedFile::fake()->image('part.jpg'),
        ])
        ->assertRedirect();

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $firstAttempt = RequestClassification::query()->where('customer_request_id', $pending->id)->first();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(RequestMatch::query()->where('customer_request_id', $pending->id)->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('customer.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestsIndexPage', false)
            ->where('requests.data.0.status_formatted.name', 'PendingClassification')
            ->where('requests.data.0.public_id', $pending->public_id)
        );

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerPortal/RequestShowPage', false)
            ->where('request.can_resume_classification', true)
            ->where('classification.public_id', $firstAttempt->public_id)
            ->where('classification.detected_item', 'ABS Sensor')
            ->where('classification.can_confirm', true)
            ->where('classification.suggested_category.public_id', $category->public_id)
            ->missing('classification.provider_response_id')
            ->missing('classification.input_tokens')
            ->has('offers', 0)
        );

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $firstAttempt), [
            'category_id' => $category->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect(route('customer.requests.show', $pending));

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($pending->fresh()->status)->toBe(RequestStatus::Ready)
        ->and($pending->fresh()->category_id)->toBe($category->id)
        ->and($firstAttempt->fresh()->customer_confirmed_category_id)->toBe($category->id)
        ->and(RequestMatch::query()->where('customer_request_id', $pending->id)->count())->toBe(1);
});

test('customer cannot confirm an unrelated category on a pending request', function () {
    $suggested = Category::factory()->create(['name_en' => 'Auto Spare Parts']);
    $override = Category::factory()->create(['name_en' => 'Plumbing']);
    $merchant = Merchant::factory()->create();
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $override->id]);
    ['user' => $user, 'customer' => $customer] = classificationCustomer(['email' => 'manual-override@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => submissionToken(),
        ]);

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    $this->actingAs($user)
        ->post(route('customer.requests.category', $pending), [
            'category_id' => $override->public_id,
            'submission_token' => submissionToken(),
            'customer_request_id' => 999,
        ])
        ->assertSessionHasErrors('customer_request_id');

    $this->actingAs($user)
        ->post(route('customer.requests.category', $pending), [
            'category_id' => $override->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertSessionHasErrors('category_id');

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification)
        ->and($pending->fresh()->category_id)->toBeNull()
        ->and(RequestMatch::query()->where('customer_request_id', $pending->id)->count())->toBe(0);
});

test('retry classification adds history on the same request and reuses the private image', function () {
    Category::factory()->create(['name_en' => 'Auto Spare Parts']);
    ['user' => $user, 'customer' => $customer] = classificationCustomer(['email' => 'retry-same@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => submissionToken(),
            'image' => UploadedFile::fake()->image('part.jpg'),
        ]);

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();
    $firstId = RequestClassification::query()->where('customer_request_id', $pending->id)->value('id');

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'Toyota Camry 2018',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect(route('customer.requests.show', $pending));

    $classifications = RequestClassification::query()->where('customer_request_id', $pending->id)->orderBy('id')->get();

    expect(CustomerRequest::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($pending->fresh()->status)->toBe(RequestStatus::PendingClassification)
        ->and($pending->fresh()->request_text)->toContain('Toyota Camry 2018')
        ->and($classifications)->toHaveCount(2)
        ->and($classifications[0]->id)->toBe($firstId)
        ->and($classifications[1]->id)->not->toBe($firstId);

    $provider = app(AiClassificationProviderInterface::class);
    expect($provider)->toBeInstanceOf(FakeClassificationProvider::class)
        ->and($provider->lastInput?->hasImage)->toBeTrue()
        ->and($provider->lastInput?->imageContents)->not->toBeEmpty();

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classification.public_id', $classifications[1]->public_id)
        );
});

test('needs more information can be resumed with clarification on the same request', function () {
    Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = classificationCustomer(['email' => 'needs-info@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'FORCE_LOW unknown part',
            'submission_token' => submissionToken(),
        ]);

    $pending = CustomerRequest::query()->where('customer_id', $customer->id)->first();

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classification.needs_more_information', true)
            ->where('classification.question', 'What vehicle make and model is this part for?')
            ->where('classification.can_confirm', true)
        );

    $this->actingAs($user)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'Honda Civic 2016',
            'submission_token' => submissionToken(),
        ])
        ->assertRedirect();

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification)
        ->and(RequestMatch::query()->where('customer_request_id', $pending->id)->count())->toBe(0)
        ->and(RequestClassification::query()->where('customer_request_id', $pending->id)->count())->toBe(2);
});

test('customer cannot resume confirm or retry another customers pending request', function () {
    $category = Category::factory()->create();
    ['user' => $userA] = classificationCustomer(['email' => 'owner-a@example.com']);
    ['user' => $userB] = classificationCustomer(['email' => 'owner-b@example.com']);

    $this->actingAs($userA)
        ->post(route('customer.requests.classify'), ['request_text' => 'ABS Sensor', 'submission_token' => submissionToken()]);

    $classification = RequestClassification::query()->first();
    $pending = $classification->customerRequest;

    $this->actingAs($userB)
        ->get(route('customer.requests.show', $pending))
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('customer.requests.classify.resume', $pending), [
            'additional_details' => 'more',
            'submission_token' => submissionToken(),
        ])
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('customer.requests.category', $pending), [
            'category_id' => $category->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('customer.requests.classifications.confirm', $classification), [
            'category_id' => $category->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertNotFound();

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification)
        ->and(CustomerRequest::query()->count())->toBe(1);
});

test('inactive suggested category cannot be confirmed from request details', function () {
    $active = Category::factory()->create(['name_en' => 'Auto Spare Parts', 'status' => CategoryStatus::Active]);
    $other = Category::factory()->create(['name_en' => 'Plumbing', 'status' => CategoryStatus::Active]);
    ['user' => $user] = classificationCustomer(['email' => 'inactive-later@example.com']);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'ABS Sensor',
            'submission_token' => submissionToken(),
        ]);

    $pending = CustomerRequest::query()->first();
    $attempt = RequestClassification::query()->first();
    $active->update(['status' => CategoryStatus::Inactive]);

    $this->actingAs($user)
        ->get(route('customer.requests.show', $pending))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classification.can_confirm', false)
            ->where('classification.suggested_category_inactive', true)
            ->where('classification.suggested_category', null)
        );

    $this->actingAs($user)
        ->post(route('customer.requests.classifications.confirm', $attempt), [
            'category_id' => $active->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertSessionHasErrors('category_id');

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification);

    $this->actingAs($user)
        ->post(route('customer.requests.category', $pending), [
            'category_id' => $other->public_id,
            'submission_token' => submissionToken(),
        ])
        ->assertSessionHasErrors('category_id');

    expect($pending->fresh()->status)->toBe(RequestStatus::PendingClassification)
        ->and($pending->fresh()->category_id)->toBeNull();
});

test('ready closed and cancelled requests cannot be classified again', function () {
    $category = Category::factory()->create();
    ['user' => $user, 'customer' => $customer] = classificationCustomer(['email' => 'no-reclassify@example.com']);

    $ready = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Ready,
        'request_text' => 'ready request',
    ]);
    $closed = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Closed,
        'request_text' => 'closed request',
    ]);
    $cancelled = CustomerRequest::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => RequestStatus::Cancelled,
        'request_text' => 'cancelled request',
    ]);

    foreach ([$ready, $closed, $cancelled] as $row) {
        $this->actingAs($user)
            ->get(route('customer.requests.show', $row))
            ->assertInertia(fn (Assert $page) => $page
                ->where('classification', null)
                ->where('request.can_resume_classification', false)
            );

        $this->actingAs($user)
            ->post(route('customer.requests.classify.resume', $row), [
                'additional_details' => 'retry',
                'submission_token' => submissionToken(),
            ])
            ->assertSessionHasErrors();

        $this->actingAs($user)
            ->post(route('customer.requests.category', $row), [
                'category_id' => $category->public_id,
                'submission_token' => submissionToken(),
            ])
            ->assertSessionHasErrors('category_id');
    }
});
