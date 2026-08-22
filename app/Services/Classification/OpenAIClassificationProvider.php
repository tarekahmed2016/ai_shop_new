<?php

namespace App\Services\Classification;

use App\Contracts\AiClassificationProviderInterface;
use App\Exceptions\ClassificationFailedException;
use App\Support\Classification\ClassificationCandidate;
use App\Support\Classification\ClassificationInput;
use App\Support\Classification\ClassificationResult;
use App\Support\Classification\ClassificationUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIClassificationProvider implements AiClassificationProviderInterface
{
    public function classify(ClassificationInput $input): ClassificationResult
    {
        $apiKey = (string) config('services.openai.api_key', '');

        if ($apiKey === '') {
            throw new ClassificationFailedException('OpenAI is not configured.');
        }

        $payload = $this->buildPayload($input);
        $timeout = max(1, (int) config('classification.timeout', 30));
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
            throw new ClassificationFailedException('The classification provider timed out.', previous: $exception);
        } catch (Throwable $exception) {
            throw new ClassificationFailedException('The classification provider is unavailable.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new ClassificationFailedException('The classification provider returned an error.');
        }

        return $this->parseResponse($response->json() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(ClassificationInput $input): array
    {
        $userContent = [
            [
                'type' => 'input_text',
                'text' => $this->userPrompt($input),
            ],
        ];

        if ($input->hasImage && is_string($input->imageContents) && $input->imageContents !== '') {
            $mime = $input->imageMime ?: 'image/jpeg';
            $userContent[] = [
                'type' => 'input_image',
                'detail' => $this->imageDetail(),
                'image_url' => 'data:'.$mime.';base64,'.base64_encode($input->imageContents),
            ];
        }

        return [
            'model' => (string) config('classification.model', 'gpt-5.6-sol'),
            'store' => false,
            'reasoning' => [
                'effort' => (string) config('classification.reasoning_effort', 'high'),
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
                    'content' => $userContent,
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'request_classification',
                    'strict' => true,
                    'schema' => $this->jsonSchema(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseResponse(array $payload): ClassificationResult
    {
        $decoded = json_decode($this->outputText($payload), true);

        if (! is_array($decoded)) {
            throw new ClassificationFailedException('The classification provider returned a malformed result.');
        }

        $alternatives = [];
        foreach ($decoded['alternatives'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['category_public_id'] ?? null)) {
                continue;
            }

            $alternatives[] = new ClassificationCandidate(
                $row['category_public_id'],
                is_numeric($row['confidence'] ?? null) ? (float) $row['confidence'] : 0.0,
            );
        }

        $primary = $decoded['primary_category_public_id'] ?? null;

        return new ClassificationResult(
            detectedItem: is_string($decoded['detected_item'] ?? null) ? $decoded['detected_item'] : null,
            confidence: is_numeric($decoded['confidence'] ?? null) ? (float) $decoded['confidence'] : null,
            primaryCategoryPublicId: is_string($primary) && $primary !== '' ? $primary : null,
            alternatives: $alternatives,
            needsMoreInformation: (bool) ($decoded['needs_more_information'] ?? false),
            question: is_string($decoded['question'] ?? null) ? $decoded['question'] : null,
            reason: is_string($decoded['reason'] ?? null) ? $decoded['reason'] : null,
            usage: $this->usageFrom($payload),
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
                'detected_item',
                'confidence',
                'primary_category_public_id',
                'alternatives',
                'needs_more_information',
                'question',
                'reason',
            ],
            'properties' => [
                'detected_item' => ['type' => ['string', 'null']],
                'confidence' => ['type' => ['number', 'null']],
                'primary_category_public_id' => ['type' => ['string', 'null']],
                'alternatives' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['category_public_id', 'confidence'],
                        'properties' => [
                            'category_public_id' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                        ],
                    ],
                ],
                'needs_more_information' => ['type' => 'boolean'],
                'question' => ['type' => ['string', 'null']],
                'reason' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedImageDetails(): array
    {
        return ['original', 'auto', 'high', 'low'];
    }

    public function imageDetail(): string
    {
        $configured = strtolower(trim((string) config('classification.image_detail', 'original')));

        return in_array($configured, self::allowedImageDetails(), true) ? $configured : 'original';
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'You classify a customer marketplace request into an existing business category.',
            'You are an assistant only. Never submit a request or choose merchants.',
            'Select category public_id values only from the supplied taxonomy.',
            'Prefer the most specific child category when it clearly matches.',
            'Do not invent categories. Do not use customer identity.',
            'Set needs_more_information true when confidence is low or the item is unclear.',
        ]);
    }

    private function userPrompt(ClassificationInput $input): string
    {
        return json_encode([
            'request_text' => $input->requestText,
            'has_image' => $input->hasImage,
            'taxonomy' => $input->taxonomy,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
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

        throw new ClassificationFailedException('The classification provider returned a malformed result.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function usageFrom(array $payload): ClassificationUsage
    {
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $inputDetails = is_array($usage['input_tokens_details'] ?? null) ? $usage['input_tokens_details'] : [];
        $outputDetails = is_array($usage['output_tokens_details'] ?? null) ? $usage['output_tokens_details'] : [];

        return new ClassificationUsage(
            responseId: is_string($payload['id'] ?? null) ? $payload['id'] : null,
            inputTokens: $this->nullableInt($usage['input_tokens'] ?? null),
            cachedInputTokens: $this->nullableInt($inputDetails['cached_tokens'] ?? $usage['cached_input_tokens'] ?? null),
            outputTokens: $this->nullableInt($usage['output_tokens'] ?? null),
            reasoningTokens: $this->nullableInt($outputDetails['reasoning_tokens'] ?? $usage['reasoning_tokens'] ?? null),
            totalTokens: $this->nullableInt($usage['total_tokens'] ?? null),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
