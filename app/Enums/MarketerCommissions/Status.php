<?php

namespace App\Enums\MarketerCommissions;

enum Status: int
{
    case Pending = 1;
    case Approved = 2;
    case Cancelled = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد المراجعة',
            self::Approved => 'معتمد',
            self::Cancelled => 'ملغى',
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
