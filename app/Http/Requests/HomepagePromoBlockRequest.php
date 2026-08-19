<?php

namespace App\Http\Requests;

use App\Enums\HomepagePromoLayout;
use App\Enums\HomepagePromoType;
use App\Http\Requests\Concerns\SanitizesRichTextInput;
use App\Support\RichTextSanitizer;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomepagePromoBlockRequest extends FormRequest
{
    use SanitizesRichTextInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('remove_badge')) {
            $this->merge([
                'remove_badge' => filter_var($this->input('remove_badge'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $this->sanitizeRichTextInput();
    }

    /**
     * @return list<string>
     */
    protected function richTextFields(): array
    {
        return RichTextSanitizer::DESCRIPTION_FIELDS;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $requiresImage = $this->isMethod('post')
            && in_array($type, [HomepagePromoType::FeatureBand->value, HomepagePromoType::PromoStrip->value], true);

        return [
            'type' => ['required', Rule::enum(HomepagePromoType::class)],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:15000'],
            'description_en' => ['nullable', 'string', 'max:15000'],
            'cta_text_ar' => ['nullable', 'string', 'max:255'],
            'cta_text_en' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'layout_variant' => ['nullable', Rule::enum(HomepagePromoLayout::class)],
            'ordering' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'image' => SafeRasterImage::rules(required: $requiresImage),
            'badge_image' => SafeRasterImage::rules(required: false),
            'remove_badge' => ['nullable', 'boolean'],
        ];
    }
}
