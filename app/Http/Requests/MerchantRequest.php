<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\Merchants\Status;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class MerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant
            ? $this->user()?->can('update', $merchant) === true
            : $this->user()?->can('create', Merchant::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', new Enum(Status::class)],
        ];

        if ($this->route('merchant')) {
            return $rules;
        }

        return array_merge($rules, [
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('categories', 'public_id')->where(
                    fn ($query) => $query->where('status', CategoryStatus::Active->value)
                ),
            ],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_email.unique' => 'A user with this email already exists. Attach them from Merchant members instead of creating a duplicate account.',
            'category_ids.required' => 'Select at least one business category.',
            'category_ids.min' => 'Select at least one business category.',
        ];
    }
}
