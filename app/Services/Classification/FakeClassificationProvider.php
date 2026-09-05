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

    public int $calls = 0;

    public function classify(ClassificationInput $input): ClassificationResult
    {
        $this->lastInput = $input;
        $this->calls++;
        $text = $input->requestText;
        $forcedContact = $this->forcedContact($input);

        if (str_contains($text, 'FORCE_FAIL') && $forcedContact === null) {
            throw new ClassificationFailedException('Forced fake provider failure.');
        }

        if ($forcedContact !== null) {
            $leaf = $this->preferredCategory($input->taxonomy);

            return new ClassificationResult(
                detectedItem: 'Contact attempt',
                confidence: 0.90,
                primaryCategoryPublicId: $leaf,
                alternatives: [],
                needsMoreInformation: false,
                question: null,
                reason: 'contact-test',
                contactInformationDetected: true,
                contactInformationTypes: $forcedContact,
                contactDetectionConfidence: 0.95,
                contactEvidenceSummary: 'contact-pattern',
            );
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

    /**
     * @return list<string>|null
     */
    private function forcedContact(ClassificationInput $input): ?array
    {
        $text = $input->requestText;
        $map = [
            'FORCE_CONTACT_PHONE' => ['phone'],
            'FORCE_CONTACT_WHATSAPP' => ['whatsapp'],
            'FORCE_CONTACT_EMAIL' => ['email'],
            'FORCE_CONTACT_URL' => ['url'],
            'FORCE_CONTACT_SOCIAL' => ['social'],
            'FORCE_CONTACT_QR' => ['qr'],
        ];

        foreach ($map as $token => $types) {
            if (str_contains($text, $token)) {
                return $types;
            }
        }

        if (! $input->hasImage) {
            return null;
        }

        $imageMap = [
            'IMAGE_CONTACT_PHONE' => ['phone'],
            'IMAGE_CONTACT_EMAIL' => ['email'],
            'IMAGE_CONTACT_URL' => ['url'],
            'IMAGE_CONTACT_QR' => ['qr'],
            'IMAGE_CONTACT_SOCIAL' => ['social'],
            'IMAGE_CONTACT_WHATSAPP' => ['whatsapp'],
        ];

        foreach ($imageMap as $token => $types) {
            if (str_contains($text, $token)) {
                return $types;
            }
        }

        return null;
    }
}
