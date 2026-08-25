<?php

namespace App\Enums\Payments;

enum Status: int
{
    case Pending = 1;
    case Paid = 2;
    case Cancelled = 3;
    case Refunded = 4;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Paid => 'مدفوع',
            self::Cancelled => 'ملغى',
            self::Refunded => 'مسترد',
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
