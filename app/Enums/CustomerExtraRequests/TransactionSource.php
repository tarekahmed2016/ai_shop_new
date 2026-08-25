<?php

namespace App\Enums\CustomerExtraRequests;

enum TransactionSource: int
{
    case BankTransfer = 1;
    case Cash = 2;
    case PromotionalBonus = 3;
    case ManualAdjustment = 4;
    case Other = 5;
    case RequestCreate = 6;

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'تحويل بنكي',
            self::Cash => 'نقدي',
            self::PromotionalBonus => 'مكافأة ترويجية',
            self::ManualAdjustment => 'تعديل يدوي',
            self::Other => 'أخرى',
            self::RequestCreate => 'إنشاء طلب',
        };
    }

    /**
     * @return list<self>
     */
    public static function manualChoices(): array
    {
        return [
            self::BankTransfer,
            self::Cash,
            self::PromotionalBonus,
            self::ManualAdjustment,
            self::Other,
        ];
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

    /**
     * @return list<array{value: int, label: string, name: string}>
     */
    public static function manualChoicesToArray(): array
    {
        return array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'name' => $case->name,
            ],
            self::manualChoices()
        );
    }
}
