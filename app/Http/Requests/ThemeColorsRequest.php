<?php

namespace App\Http\Requests;

use App\Support\ThemeColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ThemeColorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme_primary_color' => ThemeColor::rules(),
            'theme_dark_color' => ThemeColor::rules(),
            'theme_heading_text_color' => ThemeColor::rules(),
            'theme_body_text_color' => ThemeColor::rules(),
            'theme_muted_text_color' => ThemeColor::rules(),
            'theme_nav_text_color' => ThemeColor::rules(),
            'theme_nav_hover_text_color' => ThemeColor::rules(),
            'theme_hero_text_color' => ThemeColor::rules(),
            'theme_on_dark_text_color' => ThemeColor::rules(),
        ];
    }
}
