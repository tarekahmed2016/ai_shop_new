<?php

namespace App\Support\Classification;

readonly class ClassificationUsage
{
    public function __construct(
        public ?string $responseId = null,
        public ?int $inputTokens = null,
        public ?int $cachedInputTokens = null,
        public ?int $outputTokens = null,
        public ?int $reasoningTokens = null,
        public ?int $totalTokens = null,
    ) {}
}
