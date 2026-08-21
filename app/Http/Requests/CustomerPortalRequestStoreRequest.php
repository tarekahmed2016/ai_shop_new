<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status as CategoryStatus;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerPortalRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->customer?->isActive() === true;
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
                    fn ($query) => $query->where('status', CategoryStatus::Active->value)
                ),
            ],
            'request_text' => ['required', 'string', 'max:5000'],
            'image' => SafeRasterImage::rules(required: false),
            'customer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('category_id') === '') {
            $this->merge(['category_id' => null]);
        }
    }
}
