<?php

namespace App\Support\CustomerRequests;

final class NormalizedRequestSnapshot
{
    /**
     * @var list<string>
     */
    public const ALLOWED_KEYS = [
        'id',
        'category_public_id',
        'category_name_en',
        'category_name_ar',
        'item',
        'summary',
        'brand',
        'model',
        'year',
        'position',
        'quantity',
        'size',
        'variant',
        'specifications',
    ];

    /**
     * @var list<string>
     */
    public const FORBIDDEN_KEYS = [
        'request_text',
        'text',
        'additional_details',
        'image',
        'images',
        'image_url',
        'image_urls',
        'image_path',
        'path',
        'image_contents',
        'ocr',
        'ocr_text',
        'customer',
        'customer_id',
        'phone',
        'email',
        'whatsapp',
        'raw',
        'original_name',
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function sanitize(array $snapshot): array
    {
        foreach (self::FORBIDDEN_KEYS as $key) {
            unset($snapshot[$key]);
        }

        $clean = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $snapshot)) {
                continue;
            }

            $value = $snapshot[$key];
            if ($key === 'specifications' && is_array($value)) {
                $clean[$key] = self::sanitizeSpecifications($value);

                continue;
            }

            if ($key === 'id' && is_numeric($value)) {
                $clean[$key] = (int) $value;

                continue;
            }

            if ($key === 'quantity' && is_numeric($value)) {
                $clean[$key] = (int) $value;

                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                $clean[$key] = $trimmed === '' ? null : $trimmed;
            } elseif ($value === null || is_scalar($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $specifications
     * @return array<string, scalar|null>
     */
    private static function sanitizeSpecifications(array $specifications): array
    {
        $clean = [];
        foreach ($specifications as $key => $value) {
            if (! is_string($key) || in_array($key, self::FORBIDDEN_KEYS, true)) {
                continue;
            }

            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$key] = $value;

                continue;
            }

            if (is_string($value)) {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function isComparable(array $snapshot): bool
    {
        $item = $snapshot['item'] ?? $snapshot['summary'] ?? null;

        return is_string($item) && trim($item) !== '';
    }
}
