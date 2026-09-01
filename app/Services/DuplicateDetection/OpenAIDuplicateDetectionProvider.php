<?php

namespace App\Services\DuplicateDetection;

use App\Contracts\AiDuplicateDetectionProviderInterface;
use App\Exceptions\DuplicateDetectionFailedException;
use App\Support\DuplicateDetection\DuplicateDetectionInput;
use App\Support\DuplicateDetection\DuplicateDetectionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIDuplicateDetectionProvider implements AiDuplicateDetectionProviderInterface
{
    /**
     * @var list<string>
     */
    public const REASON_CODES = [
        'same_commercial_need',
        'different_specification',
        'different_item',
        'insufficient_information',
    ];

    public function detect(DuplicateDetectionInput $input): DuplicateDetectionResult
    {
        $apiKey = (string) config('services.openai.api_key', '');

        if ($apiKey === '') {
            throw new DuplicateDetectionFailedException('OpenAI is not configured.');
        }

        $payload = $this->buildPayload($input);
        $timeout = max(1, (int) config('duplicate_detection.timeout', 20));
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->connectTimeout(min(10, $timeout))
                ->baseUrl($baseUrl)
                ->post('/responses', $payload);
        } catch (ConnectionException $exception) {
            throw new DuplicateDetectionFailedException('The duplicate detection provider timed out.', previous: $exception);
        } catch (Throwable $exception) {
            throw new DuplicateDetectionFailedException('The duplicate detection provider is unavailable.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new DuplicateDetectionFailedException('The duplicate detection provider returned an error.');
        }

        return $this->parseResponse($response->json() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(DuplicateDetectionInput $input): array
    {
        return [
            'model' => (string) config('duplicate_detection.model', 'gpt-5.6-sol'),
            'store' => false,
            'reasoning' => [
                'effort' => (string) config('duplicate_detection.reasoning_effort', 'medium'),
            ],
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->systemPrompt(),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => json_encode($input->toAiPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'duplicate_request_decision',
                    'strict' => true,
                    'schema' => $this->jsonSchema(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseResponse(array $payload): DuplicateDetectionResult
    {
        $decoded = json_decode($this->outputText($payload), true);

        if (! is_array($decoded)) {
            throw new DuplicateDetectionFailedException('The duplicate detection provider returned a malformed result.');
        }

        if (! array_key_exists('is_duplicate', $decoded)) {
            throw new DuplicateDetectionFailedException('The duplicate detection provider returned invalid JSON.');
        }

        $reason = is_string($decoded['reason_code'] ?? null) ? $decoded['reason_code'] : 'insufficient_information';
        if (! in_array($reason, self::REASON_CODES, true)) {
            throw new DuplicateDetectionFailedException('The duplicate detection provider returned an invalid reason code.');
        }

        $matched = $decoded['matched_request_id'] ?? null;
        $matchedId = is_numeric($matched) ? (int) $matched : null;
        $confidence = is_numeric($decoded['confidence'] ?? null) ? max(0, min(1, round((float) $decoded['confidence'], 4))) : null;

        return new DuplicateDetectionResult(
            isDuplicate: (bool) $decoded['is_duplicate'],
            matchedRequestId: $matchedId,
            confidence: $confidence,
            reasonCode: $reason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'is_duplicate',
                'matched_request_id',
                'confidence',
                'reason_code',
            ],
            'properties' => [
                'is_duplicate' => ['type' => 'boolean'],
                'matched_request_id' => ['type' => ['integer', 'null']],
                'confidence' => ['type' => ['number', 'null']],
                'reason_code' => [
                    'type' => 'string',
                    'enum' => self::REASON_CODES,
                ],
            ],
        ];
    }

    public function systemPrompt(): string
    {
        return implode("\n", [
            'You decide whether a NEW customer marketplace request represents the same underlying commercial need as any PREVIOUS request.',
            'Judge SAME COMMERCIAL NEED, not wording similarity.',
            'Compare requested item/service, brand, model, year, part/variant, size, position, quantity when materially relevant, important specifications, and category.',
            'Different wording, synonyms, Arabic/English wording, spelling variations, or reordered phrases MUST NOT make the request appear different.',
            'A genuinely material difference in the requested item or specification means it is NOT a duplicate.',
            'Examples of duplicates: "تيل فرامل أمامي شيفروليه جروف 2023" vs "فحمات قدام لجروف موديل 23"; English vs Arabic for the same part.',
            'Examples that are NOT duplicates: front vs rear brake pads; tyres vs a battery; a different brand/model/year when that changes the needed item.',
            'If is_duplicate is true, matched_request_id MUST be the id of one previous request. Otherwise matched_request_id must be null.',
            'reason_code must be one of: same_commercial_need, different_specification, different_item, insufficient_information.',
            'Do not use customer identity. Do not invent previous requests.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputText(array $payload): string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text']) && trim($payload['output_text']) !== '') {
            return trim($payload['output_text']);
        }

        foreach ($payload['output'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (! is_array($content)) {
                    continue;
                }

                $text = $content['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }
        }

        throw new DuplicateDetectionFailedException('The duplicate detection provider returned a malformed result.');
    }
}
