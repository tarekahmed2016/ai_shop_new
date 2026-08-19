<?php

namespace App\Http\Requests;

use App\Enums\CertificateAwardType;
use App\Http\Requests\Concerns\SanitizesRichTextInput;
use App\Support\RichTextSanitizer;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificateAwardRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::enum(CertificateAwardType::class)],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'issuer_ar' => ['nullable', 'string', 'max:255'],
            'issuer_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:15000'],
            'description_en' => ['nullable', 'string', 'max:15000'],
            'issued_date' => ['nullable', 'date'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'ordering' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'image' => SafeRasterImage::rules(required: $this->isMethod('post')),
        ];
    }
}
