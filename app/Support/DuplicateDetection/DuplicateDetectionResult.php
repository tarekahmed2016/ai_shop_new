<?php

namespace App\Support\DuplicateDetection;

readonly class DuplicateDetectionResult
{
    public function __construct(
        public bool $isDuplicate,
        public ?int $matchedRequestId,
        public ?float $confidence,
        public string $reasonCode,
    ) {}

    public function shouldBlock(float $threshold): bool
    {
        if (! $this->isDuplicate) {
            return false;
        }

        if ($this->confidence === null) {
            return false;
        }

        return $this->confidence >= $threshold;
    }
}
