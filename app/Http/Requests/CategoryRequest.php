<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()?->can('update', $category) === true
            : $this->user()?->can('create', Category::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        $parentRules = [
            'nullable',
            'string',
            Rule::exists('categories', 'public_id'),
        ];

        if ($category) {
            $parentRules[] = Rule::notIn([$category->public_id]);
        }

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'parent_id' => $parentRules,
            'status' => ['required', new Enum(Status::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }

        if ($this->input('slug') === '') {
            $this->merge(['slug' => null]);
        }

        if (! $this->filled('sort_order')) {
            $this->merge(['sort_order' => 0]);
        }
    }
}
