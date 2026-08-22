<?php

namespace App\Support\Classification;

readonly class ClassificationCandidate
{
    public function __construct(
        public string $categoryPublicId,
        public float $confidence,
    ) {}

    /**
     * @return array{category_public_id: string, confidence: float}
     */
    public function toArray(): array
    {
        return [
            'category_public_id' => $this->categoryPublicId,
            'confidence' => $this->confidence,
        ];
    }
}
