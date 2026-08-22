<?php

namespace App\Support\Classification;

readonly class ClassificationResult
{
    /**
     * @param  list<ClassificationCandidate>  $alternatives
     */
    public function __construct(
        public ?string $detectedItem,
        public ?float $confidence,
        public ?string $primaryCategoryPublicId,
        public array $alternatives,
        public bool $needsMoreInformation,
        public ?string $question,
        public ?string $reason,
        public ?ClassificationUsage $usage = null,
    ) {}
}
