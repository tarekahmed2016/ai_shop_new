<?php

namespace App\Enums\Marketers;

enum Status: int
{
    case Active = 1;
    case Inactive = 2;
    case Pending = 3;
    case Rejected = 4;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Inactive => 'غير نشط',
            self::Pending => 'قيد المراجعة',
            self::Rejected => 'مرفوض',
        };
    }

    /**
     * @return list<array{value: int, label: string, name: string}>
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
