<?php

namespace App\Enums\RequestMatches;

enum Status: int
{
    case Pending = 1;
    case Viewed = 2;
    case Dismissed = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Viewed => 'تم العرض',
            self::Dismissed => 'مرفوض',
        };
    }

    public function isVisibleToMerchant(): bool
    {
        return $this === self::Pending || $this === self::Viewed;
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
