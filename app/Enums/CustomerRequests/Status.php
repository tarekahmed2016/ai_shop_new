<?php

namespace App\Enums\CustomerRequests;

enum Status: int
{
    case New = 1;
    case PendingClassification = 2;
    case Ready = 3;
    case Closed = 4;
    case Cancelled = 5;

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::PendingClassification => 'بانتظار التصنيف',
            self::Ready => 'جاهز',
            self::Closed => 'مغلق',
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
