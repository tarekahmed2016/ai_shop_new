<?php

namespace App\Enums;

enum ClientPartnerType: string
{
    case Client = 'client';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'عميل',
            self::Partner => 'شريك',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Partner => 'Partner',
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
