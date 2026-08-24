<?php

namespace App\Enums\MerchantOfferCredits;

enum TransactionType: int
{
    case InitialCredit = 1;
    case ManualAdd = 2;
    case ManualDeduct = 3;
    case OfferSubmit = 4;
    case PromotionalBonus = 5;
    case FuturePayment = 6;
    case BulkManualAdd = 7;

    public function label(): string
    {
        return match ($this) {
            self::InitialCredit => 'رصيد ابتدائي',
            self::ManualAdd => 'إضافة يدوية',
            self::ManualDeduct => 'خصم يدوي',
            self::OfferSubmit => 'تقديم عرض',
            self::PromotionalBonus => 'مكافأة ترويجية',
            self::FuturePayment => 'دفع إلكتروني',
            self::BulkManualAdd => 'إضافة جماعية',
        };
    }

    public function isConsumption(): bool
    {
        return $this === self::OfferSubmit || $this === self::ManualDeduct;
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
