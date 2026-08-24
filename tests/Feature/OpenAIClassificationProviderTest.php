<?php

use App\Contracts\AiClassificationProviderInterface;
use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\RequestClassification;
use App\Models\RequestMatch;
use App\Models\User;
use App\Services\Classification\FakeClassificationProvider;
use App\Services\Classification\OpenAIClassificationProvider;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    config([
        'classification.provider' => 'openai',
        'classification.model' => 'gpt-5.6-sol',
        'classification.reasoning_effort' => 'high',
        'services.openai.api_key' => 'sk-test-not-real',
        'services.openai.base_url' => 'https://api.openai.com/v1',
    ]);
    app()->forgetInstance(AiClassificationProviderInterface::class);
});

test('openai provider is bound when configured', function () {
    expect(app(AiClassificationProviderInterface::class))->toBeInstanceOf(OpenAIClassificationProvider::class);
});

test('openai classification persists usage and rejects invented categories', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Active, 'name_en' => 'Mobile Phones']);
    $user = User::factory()->create(['email' => 'openai-user@example.com', 'phone' => '0100999888']);
    Customer::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'phone' => $user->phone,
        'status' => CustomerStatus::Active,
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_live_mock',
            'output_text' => json_encode([
                'detected_item' => 'Phone display',
                'confidence' => 0.93,
                'primary_category_public_id' => '01INVENTEDNOTINDB00000000',
                'alternatives' => [
                    ['category_public_id' => $category->public_id, 'confidence' => 0.70],
                ],
                'needs_more_information' => false,
                'question' => null,
                'reason' => 'vision guess',
                'contact_information_detected' => false,
                'contact_information_types' => [],
                'contact_detection_confidence' => 0.1,
                'contact_evidence_summary' => null,
            ]),
            'usage' => [
                'input_tokens' => 88,
                'output_tokens' => 31,
                'total_tokens' => 119,
                'input_tokens_details' => ['cached_tokens' => 4],
                'output_tokens_details' => ['reasoning_tokens' => 11],
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Broken phone screen',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('classification.detected_item', 'Phone display')
            ->where('classification.primary.category_public_id', $category->public_id)
        );

    $row = RequestClassification::query()->latest('id')->first();

    expect($row->provider)->toBe('openai')
        ->and($row->model)->toBe('gpt-5.6-sol')
        ->and($row->provider_response_id)->toBe('resp_live_mock')
        ->and($row->input_tokens)->toBe(88)
        ->and($row->cached_input_tokens)->toBe(4)
        ->and($row->output_tokens)->toBe(31)
        ->and($row->reasoning_tokens)->toBe(11)
        ->and($row->total_tokens)->toBe(119)
        ->and($row->suggested_category_id)->toBeNull()
        ->and($row->customerRequest->status)->toBe(RequestStatus::PendingClassification)
        ->and(RequestMatch::query()->count())->toBe(0);

    Http::assertSent(function ($request) {
        return ! str_contains(json_encode($request->data()), 'openai-user@example.com')
            && ! str_contains(json_encode($request->data()), '0100999888');
    });
});

test('openai api failure becomes a failed classification without matching', function () {
    Category::factory()->create();
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id, 'status' => CustomerStatus::Active]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'unavailable']], 503),
    ]);

    $this->actingAs($user)
        ->post(route('customer.requests.classify'), [
            'request_text' => 'Something unknown',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('classification.failed', true));

    expect(RequestClassification::query()->latest('id')->first()->status)->toBe(ClassificationStatus::Failed)
        ->and(RequestMatch::query()->count())->toBe(0);
});

test('fake provider remains available when configured', function () {
    config(['classification.provider' => 'fake', 'classification.model' => 'fake-v1']);
    app()->forgetInstance(AiClassificationProviderInterface::class);

    expect(app(AiClassificationProviderInterface::class))->toBeInstanceOf(FakeClassificationProvider::class);
});
