<?php

namespace App\Enums;

enum HomepagePromoType: string
{
    case FeatureBand = 'feature_band';
    case PromoStrip = 'promo_strip';
    case BusinessCta = 'business_cta';

    public function label(): string
    {
        return match ($this) {
            self::FeatureBand => 'شريط الميزة',
            self::PromoStrip => 'شريط ترويجي',
            self::BusinessCta => 'دعوة الأعمال',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::FeatureBand => 'Feature Band',
            self::PromoStrip => 'Promo Strip',
            self::BusinessCta => 'Business CTA',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
