<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesRichTextInput;
use App\Support\RichTextSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    use Concerns\AuthorizesAdminPermission;
    use SanitizesRichTextInput;

    public function authorize(): bool
    {
        return $this->authorizeAdminMutation('pages.create', 'pages.update', 'page');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('show_in_main_menu')) {
            $this->merge([
                'show_in_main_menu' => filter_var($this->input('show_in_main_menu'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('slug') && is_string($this->input('slug'))) {
            $this->merge([
                'slug' => strtolower(trim($this->input('slug'))),
            ]);
        }

        $this->sanitizeRichTextInput();
    }

    /**
     * @return list<string>
     */
    protected function richTextFields(): array
    {
        return RichTextSanitizer::PAGE_CONTENT_FIELDS;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pageId = $this->route('page')?->id;

        return [
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'menu_title_ar' => ['nullable', 'string', 'max:255'],
            'menu_title_en' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($pageId),
            ],
            'content_ar' => ['nullable', 'string', 'max:15000'],
            'content_en' => ['nullable', 'string', 'max:15000'],
            'show_in_main_menu' => ['required', 'boolean'],
            'menu_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
