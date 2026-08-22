<?php

namespace App\Enums\MerchantOffers;

enum Status: int
{
    case Submitted = 1;
    case Withdrawn = 2;
    case Invalidated = 3;

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'مقدم',
            self::Withdrawn => 'مسحوب',
            self::Invalidated => 'ملغى',
        };
    }

    public function isActiveForCustomer(): bool
    {
        return $this === self::Submitted;
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
