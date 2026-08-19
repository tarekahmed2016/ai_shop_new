<?php

namespace App\Enums;

enum CertificateAwardType: string
{
    case Certificate = 'certificate';
    case Award = 'award';

    public function label(): string
    {
        return match ($this) {
            self::Certificate => 'شهادة',
            self::Award => 'جائزة',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Certificate => 'Certificate',
            self::Award => 'Award',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
