<?php

use App\Exceptions\ClassificationFailedException;
use App\Services\Classification\OpenAIClassificationProvider;
use App\Support\Classification\ClassificationInput;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function openaiTaxonomyInput(bool $withImage = false, string $text = 'Need an ABS sensor'): ClassificationInput
{
    return new ClassificationInput(
        requestText: $text,
        hasImage: $withImage,
        imageMime: $withImage ? 'image/jpeg' : null,
        imageSize: $withImage ? 12 : null,
        imageContents: $withImage ? 'fake-image-bytes' : null,
        taxonomy: [
            [
                'public_id' => '01TAXONOMYSPAREPARTS00001',
                'name_ar' => 'قطع غيار',
                'name_en' => 'Auto Spare Parts',
                'parent_public_id' => '01TAXONOMYVEHICLES000001',
                'parent_name_ar' => 'مركبات',
                'parent_name_en' => 'Vehicles',
            ],
        ],
    );
}

function openaiStructuredBody(array $overrides = []): array
{
    $json = json_encode(array_merge([
        'detected_item' => 'ABS Sensor',
        'confidence' => 0.91,
        'primary_category_public_id' => '01TAXONOMYSPAREPARTS00001',
        'alternatives' => [
            ['category_public_id' => '01TAXONOMYSPAREPARTS00001', 'confidence' => 0.91],
        ],
        'needs_more_information' => false,
        'question' => null,
        'reason' => 'clear spare part',
    ], $overrides), JSON_UNESCAPED_UNICODE);

    return [
        'id' => 'resp_test_123',
        'output_text' => $json,
        'usage' => [
            'input_tokens' => 120,
            'output_tokens' => 40,
            'total_tokens' => 180,
            'input_tokens_details' => ['cached_tokens' => 15],
            'output_tokens_details' => ['reasoning_tokens' => 22],
        ],
    ];
}

beforeEach(function () {
    config([
        'classification.provider' => 'openai',
        'classification.model' => 'gpt-5.6-sol',
        'classification.reasoning_effort' => 'high',
        'classification.image_detail' => 'original',
        'classification.timeout' => 30,
        'services.openai.api_key' => 'sk-test-not-real',
        'services.openai.base_url' => 'https://api.openai.com/v1',
    ]);
    Http::preventStrayRequests();
});

test('text-only request uses gpt-5.6-sol high reasoning and structured schema without pii', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(openaiStructuredBody(), 200),
    ]);

    $input = openaiTaxonomyInput(text: 'Need an ABS sensor for a Honda');
    $result = (new OpenAIClassificationProvider)->classify($input);

    expect($result->detectedItem)->toBe('ABS Sensor')
        ->and($result->confidence)->toBe(0.91)
        ->and($result->primaryCategoryPublicId)->toBe('01TAXONOMYSPAREPARTS00001')
        ->and($result->usage?->responseId)->toBe('resp_test_123')
        ->and($result->usage?->inputTokens)->toBe(120)
        ->and($result->usage?->cachedInputTokens)->toBe(15)
        ->and($result->usage?->outputTokens)->toBe(40)
        ->and($result->usage?->reasoningTokens)->toBe(22)
        ->and($result->usage?->totalTokens)->toBe(180);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $encoded = json_encode($body);

        return $request->url() === 'https://api.openai.com/v1/responses'
            && $body['model'] === 'gpt-5.6-sol'
            && ($body['store'] ?? null) === false
            && ($body['reasoning']['effort'] ?? null) === 'high'
            && ($body['text']['format']['type'] ?? null) === 'json_schema'
            && ($body['text']['format']['strict'] ?? null) === true
            && ($body['text']['format']['name'] ?? null) === 'request_classification'
            && str_contains((string) $encoded, 'Need an ABS sensor for a Honda')
            && str_contains((string) $encoded, '01TAXONOMYSPAREPARTS00001')
            && ! str_contains((string) $encoded, 'classify-user@example.com')
            && ! str_contains((string) $encoded, '0100111222')
            && ! str_contains((string) $encoded, 'wa-secret')
            && ! str_contains((string) $encoded, 'customer_id')
            && ! collect($body['input'][1]['content'])->contains(fn ($part) => ($part['type'] ?? null) === 'input_image');
    });
});

test('image input is sent as a backend data url not a public path', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(openaiStructuredBody(), 200),
    ]);

    (new OpenAIClassificationProvider)->classify(openaiTaxonomyInput(withImage: true));

    Http::assertSent(function ($request) {
        $image = collect($request->data()['input'][1]['content'] ?? [])
            ->first(fn ($part) => ($part['type'] ?? null) === 'input_image');

        return is_array($image)
            && ($image['detail'] ?? null) === 'original'
            && str_starts_with((string) ($image['image_url'] ?? ''), 'data:image/jpeg;base64,')
            && ! str_contains((string) ($image['image_url'] ?? ''), 'customer-requests/')
            && ! str_contains(json_encode($request->data()), 'storage/');
    });
});

test('malformed provider json is handled', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['id' => 'resp_bad', 'output_text' => 'not-json'], 200),
    ]);

    expect(fn () => (new OpenAIClassificationProvider)->classify(openaiTaxonomyInput()))
        ->toThrow(ClassificationFailedException::class);
});

test('api error is handled without a successful classification', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    expect(fn () => (new OpenAIClassificationProvider)->classify(openaiTaxonomyInput()))
        ->toThrow(ClassificationFailedException::class);
});

test('provider timeout is handled', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: timeout');
    });

    expect(fn () => (new OpenAIClassificationProvider)->classify(openaiTaxonomyInput()))
        ->toThrow(ClassificationFailedException::class);
});

test('missing usage subfields stay null and do not break parsing', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_no_usage',
            'output_text' => json_encode([
                'detected_item' => 'Phone screen',
                'confidence' => 0.88,
                'primary_category_public_id' => '01TAXONOMYSPAREPARTS00001',
                'alternatives' => [],
                'needs_more_information' => false,
                'question' => null,
                'reason' => 'ok',
            ]),
        ], 200),
    ]);

    $result = (new OpenAIClassificationProvider)->classify(openaiTaxonomyInput());

    expect($result->detectedItem)->toBe('Phone screen')
        ->and($result->usage?->responseId)->toBe('resp_no_usage')
        ->and($result->usage?->inputTokens)->toBeNull()
        ->and($result->usage?->totalTokens)->toBeNull();
});

test('invalid image detail configuration falls back to original', function () {
    config(['classification.image_detail' => 'ultra-max']);
    $provider = new OpenAIClassificationProvider;

    expect($provider->imageDetail())->toBe('original');

    $payload = $provider->buildPayload(openaiTaxonomyInput(withImage: true));
    $image = collect($payload['input'][1]['content'])->first(fn ($part) => ($part['type'] ?? null) === 'input_image');

    expect($payload['store'])->toBeFalse()
        ->and($image['detail'] ?? null)->toBe('original');
});
