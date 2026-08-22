<?php

namespace App\Services\Classification;

use App\Contracts\AiClassificationProviderInterface;
use App\Exceptions\ClassificationFailedException;
use App\Support\Classification\ClassificationCandidate;
use App\Support\Classification\ClassificationInput;
use App\Support\Classification\ClassificationResult;

class FakeClassificationProvider implements AiClassificationProviderInterface
{
    public ?ClassificationInput $lastInput = null;

    public function classify(ClassificationInput $input): ClassificationResult
    {
        $this->lastInput = $input;
        $text = $input->requestText;

        if (str_contains($text, 'FORCE_FAIL')) {
            throw new ClassificationFailedException('Forced fake provider failure.');
        }

        if (str_contains($text, 'FORCE_MALFORMED')) {
            return new ClassificationResult(
                detectedItem: 'Unknown item',
                confidence: 0.91,
                primaryCategoryPublicId: '01NOTAREALCATEGORYID00000',
                alternatives: [
                    new ClassificationCandidate('01FAKEALTERNATIVECATEGORY0', 0.70),
                ],
                needsMoreInformation: false,
                question: null,
                reason: 'malformed-test',
            );
        }

        if (preg_match('/FORCE_INACTIVE\s+(\S+)/', $text, $matches) === 1) {
            return new ClassificationResult(
                detectedItem: 'Unknown item',
                confidence: 0.91,
                primaryCategoryPublicId: $matches[1],
                alternatives: [],
                needsMoreInformation: false,
                question: null,
                reason: 'inactive-test',
            );
        }

        $leaf = $this->preferredCategory($input->taxonomy);
        $second = $input->taxonomy[1]['public_id'] ?? $leaf;
        $third = $input->taxonomy[2]['public_id'] ?? $second;

        if (str_contains($text, 'FORCE_LOW')) {
            return new ClassificationResult(
                detectedItem: 'Vehicle electrical component',
                confidence: 0.45,
                primaryCategoryPublicId: null,
                alternatives: array_values(array_filter([
                    $leaf !== null ? new ClassificationCandidate($leaf, 0.45) : null,
                    $second !== $leaf && $second !== null ? new ClassificationCandidate($second, 0.41) : null,
                ])),
                needsMoreInformation: true,
                question: 'What vehicle make and model is this part for?',
                reason: 'low-confidence-test',
            );
        }

        if (str_contains($text, 'FORCE_MEDIUM')) {
            $alternatives = [];
            foreach ([$leaf, $second, $third] as $index => $publicId) {
                if (! is_string($publicId) || $publicId === '') {
                    continue;
                }
                if (collect($alternatives)->contains(fn (ClassificationCandidate $row) => $row->categoryPublicId === $publicId)) {
                    continue;
                }
                $alternatives[] = new ClassificationCandidate($publicId, max(0.61, 0.72 - ($index * 0.05)));
            }

            return new ClassificationResult(
                detectedItem: 'Auto electrical component',
                confidence: 0.70,
                primaryCategoryPublicId: $leaf,
                alternatives: $alternatives,
                needsMoreInformation: false,
                question: null,
                reason: 'medium-confidence-test',
            );
        }

        return new ClassificationResult(
            detectedItem: str_contains(strtoupper($text), 'ABS') ? 'ABS Sensor' : 'Mobile phone display',
            confidence: 0.90,
            primaryCategoryPublicId: $leaf,
            alternatives: [],
            needsMoreInformation: false,
            question: null,
            reason: 'high-confidence-test',
        );
    }

    /**
     * @param  list<array{public_id: string, parent_public_id: ?string}>  $taxonomy
     */
    private function preferredCategory(array $taxonomy): ?string
    {
        if ($taxonomy === []) {
            return null;
        }

        foreach (array_reverse($taxonomy) as $row) {
            if (! empty($row['parent_public_id'])) {
                return $row['public_id'];
            }
        }

        return $taxonomy[0]['public_id'];
    }
}
