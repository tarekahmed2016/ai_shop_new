<?php

namespace App\Enums\Payments;

enum Type: int
{
    case MerchantOfferCredits = 1;
    case CustomerExtraRequests = 2;
    case Subscription = 3;
    case Other = 4;

    public function label(): string
    {
        return match ($this) {
            self::MerchantOfferCredits => 'رصيد عروض التاجر',
            self::CustomerExtraRequests => 'طلبات إضافية للعميل',
            self::Subscription => 'اشتراك',
            self::Other => 'أخرى',
        };
    }

    public function capabilityLabel(): string
    {
        return match ($this) {
            self::MerchantOfferCredits => 'تاجر',
            self::CustomerExtraRequests => 'عميل',
            self::Subscription, self::Other => 'أخرى',
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
