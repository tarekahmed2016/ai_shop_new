<?php

namespace App\Enums\CustomerRequests;

enum Source: string
{
    case Admin = 'admin';
    case WhatsApp = 'whatsapp';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'لوحة التحكم',
            self::WhatsApp => 'واتساب',
            self::Web => 'الويب',
        };
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'name' => $case->name,
            ],
            self::cases()
        );
    }
}
