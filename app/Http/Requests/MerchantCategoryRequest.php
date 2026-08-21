<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MerchantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MerchantCategory::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'string',
                Rule::exists('categories', 'public_id')->where(
                    fn ($query) => $query->where('status', Status::Active->value)
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Merchant|null $merchant */
            $merchant = $this->route('merchant');
            $categoryPublicId = $this->input('category_id');

            if (! $merchant instanceof Merchant || ! is_string($categoryPublicId) || $categoryPublicId === '') {
                return;
            }

            $category = Category::query()->where('public_id', $categoryPublicId)->first();

            if ($category === null) {
                return;
            }

            $duplicate = MerchantCategory::query()
                ->where('merchant_id', $merchant->id)
                ->where('category_id', $category->id)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('category_id', 'This category is already assigned to the merchant.');
            }
        });
    }
}
