<?php

use App\Exceptions\DuplicateDetectionFailedException;
use App\Services\DuplicateDetection\OpenAIDuplicateDetectionProvider;
use App\Support\DuplicateDetection\DuplicateDetectionInput;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function openaiDuplicateInput(): DuplicateDetectionInput
{
    return new DuplicateDetectionInput(
        newRequest: [
            'item' => 'chevrolet groove 2023 front brake pads',
            'category_name_en' => 'Auto Spare Parts',
            'summary' => 'auto spare parts chevrolet groove 2023 front brake pads',
        ],
        previousRequests: [
            [
                'id' => 41,
                'item' => 'front brake pads chevrolet groove 2023',
                'category_name_en' => 'Auto Spare Parts',
                'summary' => 'auto spare parts front brake pads chevrolet groove 2023',
            ],
        ],
    );
}

function openaiDuplicateBody(array $overrides = []): array
{
    $json = json_encode(array_merge([
        'is_duplicate' => true,
        'matched_request_id' => 41,
        'confidence' => 0.98,
        'reason_code' => 'same_commercial_need',
    ], $overrides), JSON_UNESCAPED_UNICODE);

    return [
        'id' => 'resp_dup_123',
        'output_text' => $json,
    ];
}

beforeEach(function () {
    config([
        'duplicate_detection.provider' => 'openai',
        'duplicate_detection.model' => 'gpt-5.6-sol',
        'duplicate_detection.reasoning_effort' => 'medium',
        'duplicate_detection.timeout' => 20,
        'services.openai.api_key' => 'sk-test-not-real',
        'services.openai.base_url' => 'https://api.openai.com/v1',
    ]);
    Http::preventStrayRequests();
});

test('duplicate detection payload is compact structured json with a commercial-need prompt', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(openaiDuplicateBody(), 200),
    ]);

    $input = openaiDuplicateInput();
    $provider = new OpenAIDuplicateDetectionProvider;
    $result = $provider->detect($input);

    expect($result->isDuplicate)->toBeTrue()
        ->and($result->matchedRequestId)->toBe(41)
        ->and($result->confidence)->toBe(0.98)
        ->and($result->reasonCode)->toBe('same_commercial_need');

    Http::assertSent(function ($request) {
        $body = $request->data();
        $encoded = json_encode($body);

        return ($body['model'] ?? null) === 'gpt-5.6-sol'
            && ($body['text']['format']['name'] ?? null) === 'duplicate_request_decision'
            && str_contains((string) $encoded, 'SAME COMMERCIAL NEED')
            && str_contains((string) $encoded, 'chevrolet groove 2023 front brake pads')
            && str_contains((string) $encoded, 'previous_requests')
            && ! str_contains((string) $encoded, 'request_text')
            && ! str_contains((string) $encoded, 'image_url');
    });
});

test('malformed duplicate json and timeouts fail as provider errors', function () {
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['output_text' => 'not-json'], 200),
    ]);

    expect(fn () => (new OpenAIDuplicateDetectionProvider)->detect(openaiDuplicateInput()))
        ->toThrow(DuplicateDetectionFailedException::class);

    Http::fake([
        'https://api.openai.com/v1/responses' => function () {
            throw new ConnectionException('timeout');
        },
    ]);

    expect(fn () => (new OpenAIDuplicateDetectionProvider)->detect(openaiDuplicateInput()))
        ->toThrow(DuplicateDetectionFailedException::class);
});
