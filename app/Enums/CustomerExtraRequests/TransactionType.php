<?php

namespace App\Enums\CustomerExtraRequests;

enum TransactionType: int
{
    case ManualAdd = 1;
    case ManualDeduct = 2;
    case PromotionalBonus = 3;
    case BulkManualAdd = 4;
    case RequestCreate = 5;

    public function label(): string
    {
        return match ($this) {
            self::ManualAdd => 'إضافة يدوية',
            self::ManualDeduct => 'خصم يدوي',
            self::PromotionalBonus => 'مكافأة ترويجية',
            self::BulkManualAdd => 'إضافة جماعية',
            self::RequestCreate => 'إنشاء طلب',
        };
    }

    public function isConsumption(): bool
    {
        return $this === self::RequestCreate || $this === self::ManualDeduct;
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
