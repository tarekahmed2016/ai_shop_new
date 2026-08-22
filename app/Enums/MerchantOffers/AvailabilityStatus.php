<?php

namespace App\Enums\MerchantOffers;

enum AvailabilityStatus: int
{
    case Available = 1;
    case Limited = 2;
    case OnRequest = 3;

    public function label(): string
    {
        return match ($this) {
            self::Available => 'متوفر',
            self::Limited => 'كمية محدودة',
            self::OnRequest => 'حسب الطلب',
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
