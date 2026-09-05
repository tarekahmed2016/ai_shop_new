<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesRichTextInput;
use App\Support\RichTextSanitizer;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TeamMemberRequest extends FormRequest
{
    use Concerns\AuthorizesAdminPermission;
    use SanitizesRichTextInput;

    public function authorize(): bool
    {
        return $this->authorizeAdminMutation('team-members.create', 'team-members.update', 'teamMember');
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
        return RichTextSanitizer::BIO_FIELDS;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'position_ar' => ['nullable', 'string', 'max:255'],
            'position_en' => ['nullable', 'string', 'max:255'],
            'bio_ar' => ['nullable', 'string', 'max:15000'],
            'bio_en' => ['nullable', 'string', 'max:15000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
            'ordering' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'image' => SafeRasterImage::rules(required: $this->isMethod('post')),
        ];
    }
}
