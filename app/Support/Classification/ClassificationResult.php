<?php

namespace App\Support\Classification;

readonly class ClassificationResult
{
    /**
     * @param  list<ClassificationCandidate>  $alternatives
     * @param  list<string>  $contactInformationTypes
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
        public bool $contactInformationDetected = false,
        public array $contactInformationTypes = [],
        public ?float $contactDetectionConfidence = null,
        public ?string $contactEvidenceSummary = null,
    ) {}
}
