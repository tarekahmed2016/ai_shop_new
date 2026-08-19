<?php

namespace App\Support;

class ThemeColor
{
    public const DEFAULT_PRIMARY = '#FFD400';

    public const DEFAULT_DARK = '#111111';

    public const DEFAULT_HEADING_TEXT = '#111111';

    public const DEFAULT_BODY_TEXT = '#111111';

    public const DEFAULT_MUTED_TEXT = '#666666';

    public const DEFAULT_NAV_TEXT = '#FFFFFF';

    public const DEFAULT_HERO_TEXT = '#FFFFFF';

    public const DEFAULT_ON_DARK_TEXT = '#FFFFFF';

    /**
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'theme_primary_color' => self::DEFAULT_PRIMARY,
        'theme_dark_color' => self::DEFAULT_DARK,
        'theme_heading_text_color' => self::DEFAULT_HEADING_TEXT,
        'theme_body_text_color' => self::DEFAULT_BODY_TEXT,
        'theme_muted_text_color' => self::DEFAULT_MUTED_TEXT,
        'theme_nav_text_color' => self::DEFAULT_NAV_TEXT,
        'theme_nav_hover_text_color' => self::DEFAULT_PRIMARY,
        'theme_hero_text_color' => self::DEFAULT_HERO_TEXT,
        'theme_on_dark_text_color' => self::DEFAULT_ON_DARK_TEXT,
    ];

    /**
     * @return list<string>
     */
    public static function fieldNames(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
    }

    public static function normalize(?string $color): ?string
    {
        if ($color === null || $color === '') {
            return null;
        }

        $color = strtoupper(trim($color));

        if (! preg_match('/^#[0-9A-F]{6}$/', $color)) {
            return null;
        }

        return $color;
    }

    public static function resolve(?string $color, string $default): string
    {
        return self::normalize($color) ?? $default;
    }

    public static function resolvePrimary(?string $color): string
    {
        return self::resolve($color, self::DEFAULT_PRIMARY);
    }

    public static function resolveDark(?string $color): string
    {
        return self::resolve($color, self::DEFAULT_DARK);
    }

    /**
     * @return array<string, string>
     */
    public static function resolvedFor(?object $companyInfo): array
    {
        $primary = self::resolvePrimary($companyInfo->theme_primary_color ?? null);

        return [
            'theme_primary_color' => $primary,
            'theme_dark_color' => self::resolveDark($companyInfo->theme_dark_color ?? null),
            'theme_heading_text_color' => self::resolve($companyInfo->theme_heading_text_color ?? null, self::DEFAULT_HEADING_TEXT),
            'theme_body_text_color' => self::resolve($companyInfo->theme_body_text_color ?? null, self::DEFAULT_BODY_TEXT),
            'theme_muted_text_color' => self::resolve($companyInfo->theme_muted_text_color ?? null, self::DEFAULT_MUTED_TEXT),
            'theme_nav_text_color' => self::resolve($companyInfo->theme_nav_text_color ?? null, self::DEFAULT_NAV_TEXT),
            'theme_nav_hover_text_color' => self::resolve($companyInfo->theme_nav_hover_text_color ?? null, $primary),
            'theme_hero_text_color' => self::resolve($companyInfo->theme_hero_text_color ?? null, self::DEFAULT_HERO_TEXT),
            'theme_on_dark_text_color' => self::resolve($companyInfo->theme_on_dark_text_color ?? null, self::DEFAULT_ON_DARK_TEXT),
        ];
    }
}
