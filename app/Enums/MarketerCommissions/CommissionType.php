<?php

namespace App\Enums\MarketerCommissions;

enum CommissionType: int
{
    case Percentage = 1;

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'نسبة مئوية',
        };
    }
}
