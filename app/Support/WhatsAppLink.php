<?php

namespace App\Support;

class WhatsAppLink
{
    /**
     * Normalize a phone number for wa.me (digits only, no leading +).
     * 8-digit local numbers receive the configured default country code.
     */
    public static function digits(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $value = trim($phone);
        if ($value === '') {
            return null;
        }

        $value = str_replace([' ', '-', '(', ')'], '', $value);
        $value = ltrim($value, '+');

        if (str_starts_with($value, '00')) {
            $value = substr($value, 2);
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        $countryCode = self::defaultCountryCode();

        if (strlen($digits) === 8 && ! str_starts_with($digits, '0')) {
            $digits = $countryCode.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return null;
        }

        return $digits;
    }

    public static function url(?string $phone, string $message): ?string
    {
        $digits = self::digits($phone);

        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    private static function defaultCountryCode(): string
    {
        $code = preg_replace('/\D+/', '', (string) config('services.whatsapp.default_country_code', '968')) ?? '';

        return $code !== '' ? $code : '968';
    }
}
