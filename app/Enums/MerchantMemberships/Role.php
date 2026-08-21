<?php

namespace App\Enums\MerchantMemberships;

enum Role: string
{
    case Owner = 'merchant-owner';
    case Manager = 'merchant-manager';
    case Staff = 'merchant-staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالك التاجر',
            self::Manager => 'مدير التاجر',
            self::Staff => 'موظف التاجر',
        };
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
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
