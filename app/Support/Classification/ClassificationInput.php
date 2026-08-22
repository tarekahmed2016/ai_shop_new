<?php

namespace App\Support\Classification;

readonly class ClassificationInput
{
    /**
     * @param  list<array{public_id: string, name_ar: string, name_en: string, parent_public_id: ?string, parent_name_ar: ?string, parent_name_en: ?string}>  $taxonomy
     */
    public function __construct(
        public string $requestText,
        public bool $hasImage,
        public ?string $imageMime,
        public ?int $imageSize,
        public ?string $imageContents,
        public array $taxonomy,
    ) {}

    /**
     * Safe snapshot for tests: never includes customer identity.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'request_text' => $this->requestText,
            'has_image' => $this->hasImage,
            'image_mime' => $this->imageMime,
            'image_size' => $this->imageSize,
            'taxonomy_public_ids' => array_map(fn (array $row) => $row['public_id'], $this->taxonomy),
        ];
    }
}
