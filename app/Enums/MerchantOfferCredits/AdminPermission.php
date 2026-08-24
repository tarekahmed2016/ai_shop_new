<?php

namespace App\Enums\MerchantOfferCredits;

enum AdminPermission: string
{
    case View = 'merchant-credits.view';
    case Add = 'merchant-credits.add';
    case Deduct = 'merchant-credits.deduct';
    case ManageSettings = 'merchant-credits.manage-settings';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
