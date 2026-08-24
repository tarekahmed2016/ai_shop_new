<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ReferralCode
{
    public const PATTERN = '/^[A-Z0-9]{4,16}$/';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        if ($normalized === '' || preg_match(self::PATTERN, $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    public static function generate(): string
    {
        return strtoupper(Str::random(8));
    }
}
