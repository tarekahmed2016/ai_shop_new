<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

class RichTextSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    /**
     * @var list<string>
     */
    public const COMPANY_INFO_FIELDS = [
        'hero_description_ar',
        'hero_description_en',
        'about_ar',
        'about_en',
        'vision_ar',
        'vision_en',
        'mission_ar',
        'mission_en',
    ];

    /**
     * @var list<string>
     */
    public const DESCRIPTION_FIELDS = [
        'description_ar',
        'description_en',
    ];

    /**
     * @var list<string>
     */
    public const BIO_FIELDS = [
        'bio_ar',
        'bio_en',
    ];

    /**
     * @var list<string>
     */
    public const PAGE_CONTENT_FIELDS = [
        'content_ar',
        'content_en',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $purified = self::purifier()->purify($html);

        return self::enforceLinkRelPolicy(self::enforceImageSourcePolicy($purified));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function sanitizeFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = self::sanitize($data[$field]);
            }
        }

        return $data;
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'p[style|class]',
            'br',
            'h2[style|class]',
            'h3[style|class]',
            'h4[style|class]',
            'strong,b,em,i,u,s,strike,del',
            'span[style|class]',
            'ul,ol,li[style|class]',
            'blockquote[style|class]',
            'a[href|target|rel|title]',
            'hr',
            'figure[style|class]',
            'figcaption',
            'img[src|alt|width|height|class|style]',
            'table[class|style]',
            'thead,tbody,tr',
            'th[colspan|rowspan|style|class]',
            'td[colspan|rowspan|style|class]',
        ]));
        $config->set('HTML.ForbiddenElements', ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form', 'input', 'button']);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('URI.DisableExternalResources', false);
        $config->set('CSS.AllowedProperties', [
            'text-align',
            'color',
            'background-color',
            'font-size',
            'font-family',
            'width',
            'height',
        ]);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('HTML.DefinitionID', 'company-profile-rich-text');
        $config->set('HTML.DefinitionRev', 2);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Inline', 'Common');
        }

        self::$purifier = new HTMLPurifier($config);

        return self::$purifier;
    }

    private static function enforceImageSourcePolicy(string $html): string
    {
        return (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $matches) {
            $tag = $matches[0];

            if (! preg_match('/\ssrc=(["\'])(.*?)\1/i', $tag, $srcMatch)) {
                return '';
            }

            $src = $srcMatch[2];

            if (self::isAllowedRichTextImageSrc($src)) {
                return $tag;
            }

            return '';
        }, $html);
    }

    private static function enforceLinkRelPolicy(string $html): string
    {
        return (string) preg_replace_callback('/<a\b[^>]*>/i', function (array $matches) {
            $tag = $matches[0];

            if (! preg_match('/\starget=(["\'])_blank\1/i', $tag)) {
                return $tag;
            }

            if (preg_match('/\srel=(["\'])(.*?)\1/i', $tag, $relMatch)) {
                $relValues = preg_split('/\s+/', strtolower(trim($relMatch[2]))) ?: [];
                $required = ['noopener', 'noreferrer'];
                $merged = array_values(array_unique(array_merge($relValues, $required)));
                $rel = implode(' ', $merged);

                return (string) preg_replace(
                    '/\srel=(["\']).*?\1/i',
                    ' rel="'.htmlspecialchars($rel, ENT_QUOTES).'"',
                    $tag,
                    1,
                );
            }

            return preg_replace('/<a\b/i', '<a rel="noopener noreferrer"', $tag, 1);
        }, $html);
    }

    private static function isAllowedRichTextImageSrc(string $src): bool
    {
        if ($src === '') {
            return false;
        }

        if (preg_match('#^data:#i', $src)) {
            return false;
        }

        if (preg_match('#^(javascript|vbscript|file):#i', $src)) {
            return false;
        }

        if (preg_match('#^/storage/rich-text/.+\.(?:jpe?g|png|webp)$#i', $src)) {
            return true;
        }

        $path = parse_url($src, PHP_URL_PATH);

        return is_string($path) && (bool) preg_match('#^/storage/rich-text/.+\.(?:jpe?g|png|webp)$#i', $path);
    }
}
