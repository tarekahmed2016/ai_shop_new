<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesRichTextInput;
use App\Rules\SafeHttpUrl;
use App\Support\RichTextSanitizer;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyInfoRequest extends FormRequest
{
    use SanitizesRichTextInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeRichTextInput();
    }

    /**
     * @return list<string>
     */
    protected function richTextFields(): array
    {
        return RichTextSanitizer::COMPANY_INFO_FIELDS;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'hero_title_ar' => ['nullable', 'string', 'max:255'],
            'hero_title_en' => ['nullable', 'string', 'max:255'],
            'hero_description_ar' => ['nullable', 'string', 'max:15000'],
            'hero_description_en' => ['nullable', 'string', 'max:15000'],
            'about_ar' => ['nullable', 'string', 'max:15000'],
            'about_en' => ['nullable', 'string', 'max:15000'],
            'vision_ar' => ['nullable', 'string', 'max:15000'],
            'vision_en' => ['nullable', 'string', 'max:15000'],
            'mission_ar' => ['nullable', 'string', 'max:15000'],
            'mission_en' => ['nullable', 'string', 'max:15000'],
            'address_ar' => ['nullable', 'string', 'max:1000'],
            'address_en' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', new SafeHttpUrl],
            'facebook' => ['nullable', new SafeHttpUrl],
            'instagram' => ['nullable', new SafeHttpUrl],
            'linkedin' => ['nullable', new SafeHttpUrl],
            'x_twitter' => ['nullable', new SafeHttpUrl],
            'youtube' => ['nullable', new SafeHttpUrl],
            'tiktok' => ['nullable', new SafeHttpUrl],
            'snapchat' => ['nullable', new SafeHttpUrl],
            'whatsapp' => ['nullable', new SafeHttpUrl],
            'logo' => SafeRasterImage::rules(),
        ];
    }
}
