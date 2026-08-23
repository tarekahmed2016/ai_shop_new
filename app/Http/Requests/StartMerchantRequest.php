<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status as CategoryStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('categories', 'public_id')->where(
                    fn ($query) => $query->where('status', CategoryStatus::Active->value)
                ),
            ],
            'user_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'merchant_user_id' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
            'owner_name' => ['prohibited'],
            'owner_email' => ['prohibited'],
            'owner_phone' => ['prohibited'],
            'password' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_ids.required' => 'Select at least one business category.',
            'category_ids.min' => 'Select at least one business category.',
        ];
    }
}
