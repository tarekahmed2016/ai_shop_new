<?php

namespace App\Enums;

enum HomepagePromoLayout: string
{
    case ContentLeft = 'content_left';
    case ContentRight = 'content_right';

    public function label(): string
    {
        return match ($this) {
            self::ContentLeft => 'المحتوى يساراً',
            self::ContentRight => 'المحتوى يميناً',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::ContentLeft => 'Content Left',
            self::ContentRight => 'Content Right',
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
