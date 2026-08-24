<?php

namespace App\Services;

use App\Support\Classification\ClassificationResult;
use App\Support\CustomerRequests\ContactInformationScanResult;

class ContactInformationScanner
{
    /**
     * @var list<string>
     */
    public const TYPES = ['phone', 'whatsapp', 'email', 'url', 'social', 'qr'];

    public function scanText(string $text): ContactInformationScanResult
    {
        $types = [];

        if ($this->containsEmail($text)) {
            $types[] = 'email';
        }

        $withoutEmails = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', ' ', $text) ?? $text;

        if ($this->containsUrl($withoutEmails)) {
            $types[] = 'url';
        }

        if ($this->containsWhatsApp($text)) {
            $types[] = 'whatsapp';
        }

        if ($this->containsSocialContact($text)) {
            $types[] = 'social';
        }

        if ($this->containsContactPhone($text)) {
            $types[] = 'phone';
        }

        $types = array_values(array_unique($types));

        return new ContactInformationScanResult(
            detected: $types !== [],
            types: $types,
            layer: 'deterministic',
        );
    }

    public function confirmedFromAi(ClassificationResult $result): bool
    {
        if (! $result->contactInformationDetected) {
            return false;
        }

        $confidence = $result->contactDetectionConfidence ?? $result->confidence ?? 0.0;
        $threshold = (float) config('customer_requests.ai_contact_confidence', 0.6);

        return $confidence >= $threshold;
    }

    /**
     * @return list<string>
     */
    public function sanitizedTypes(array $types): array
    {
        $allowed = array_flip(self::TYPES);

        return array_values(array_unique(array_filter(
            $types,
            fn (mixed $type): bool => is_string($type) && isset($allowed[$type]),
        )));
    }

    private function containsEmail(string $text): bool
    {
        return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text) === 1;
    }

    private function containsUrl(string $text): bool
    {
        return preg_match(
            '/https?:\/\/|www\.|wa\.me\/|t\.me\/|(?<![\w.])(?:[a-z0-9\-]+\.)+(?:com|net|org|io|me|app|om|co)\b/i',
            $text,
        ) === 1;
    }

    private function containsWhatsApp(string $text): bool
    {
        return preg_match('/whats?app|واتس(?:اب)?|واتس(?:اب)?|wa\.me/iu', $text) === 1;
    }

    private function containsSocialContact(string $text): bool
    {
        $mentionsNetwork = preg_match(
            '/instagram|telegram|snapchat|facebook|tiktok|x\.com|twitter|انست[قغ]رام|تيليجرام|سناب/iu',
            $text,
        ) === 1;
        $hasHandle = preg_match('/@[A-Za-z0-9._]{2,}/', $text) === 1;
        $hasContactIntent = preg_match(
            '/\b(contact|follow|message|dm|reach)\b|تواصل|كلمني|راسلني/iu',
            $text,
        ) === 1;

        if ($mentionsNetwork && ($hasHandle || $hasContactIntent)) {
            return true;
        }

        return $hasHandle && $hasContactIntent;
    }

    private function containsContactPhone(string $text): bool
    {
        $hasContactVerb = preg_match(
            '/\b(call|phone|mobile|whatsapp|contact|reach|text me|message me|whats\s?app)\b|اتصل|كلمني|رقم(?:\s+ال)?(?:جوال|هاتف|واتس)?|تواصل|جوالي|هاتفي|واتس/iu',
            $text,
        ) === 1;

        if (! $hasContactVerb) {
            return false;
        }

        return preg_match('/\+?\d[\d\s\-()]{6,}|\b9\d{7}\b|9x{6,}/i', $text) === 1;
    }
}
