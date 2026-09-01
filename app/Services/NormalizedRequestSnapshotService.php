<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use App\Support\Classification\ClassificationResult;
use App\Support\CustomerRequests\NormalizedRequestSnapshot;

class NormalizedRequestSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function fromClassificationResult(ClassificationResult $result, ?Category $category): array
    {
        $item = is_string($result->detectedItem) ? trim($result->detectedItem) : '';

        return NormalizedRequestSnapshot::sanitize([
            'category_public_id' => $category?->public_id,
            'category_name_en' => $category?->name_en,
            'category_name_ar' => $category?->name_ar,
            'item' => $item !== '' ? $item : null,
            'summary' => $this->summary($item, $category),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromPersisted(CustomerRequest $request, ?RequestClassification $classification = null, ?Category $category = null): array
    {
        $stored = is_array($request->normalized_request_json) ? $request->normalized_request_json : [];
        if (NormalizedRequestSnapshot::isComparable($stored)) {
            $snapshot = NormalizedRequestSnapshot::sanitize($stored);
            $snapshot['id'] = (int) $request->id;

            return $snapshot;
        }

        $classification ??= $request->latestClassification()->first();
        $item = is_string($classification?->detected_item) ? trim($classification->detected_item) : '';
        $category ??= $request->category
            ?? $classification?->confirmedCategory
            ?? $classification?->suggestedCategory;

        return NormalizedRequestSnapshot::sanitize([
            'id' => (int) $request->id,
            'category_public_id' => $category?->public_id,
            'category_name_en' => $category?->name_en,
            'category_name_ar' => $category?->name_ar,
            'item' => $item !== '' ? $item : null,
            'summary' => $this->summary($item, $category),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function store(CustomerRequest $request, array $snapshot): void
    {
        $clean = NormalizedRequestSnapshot::sanitize($snapshot);
        unset($clean['id']);
        $request->normalized_request_json = $clean;
        $request->save();
    }

    private function summary(string $item, ?Category $category): ?string
    {
        $parts = array_values(array_filter([
            $category?->name_en ?: $category?->name_ar,
            $item !== '' ? $item : null,
        ]));

        if ($parts === []) {
            return null;
        }

        return mb_strtolower(implode(' ', $parts));
    }
}
