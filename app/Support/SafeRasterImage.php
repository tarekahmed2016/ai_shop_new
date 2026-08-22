<?php

namespace App\Support;

class SafeRasterImage
{
    /**
     * @return list<string>
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:jpg,jpeg,png,webp',
            'max:4096',
        ];
    }

    /**
     * @return list<string>
     */
    public static function inlineUploadRules(): array
    {
        return [
            'required',
            'file',
            'mimes:jpg,jpeg,png,webp',
            'max:5120',
            'dimensions:max_width=8000,max_height=8000',
        ];
    }

    /**
     * @return list<string>
     */
    public static function offerRules(): array
    {
        return [
            'file',
            'mimes:jpg,jpeg,png,webp',
            'max:5120',
            'dimensions:max_width=8000,max_height=8000',
        ];
    }
}
