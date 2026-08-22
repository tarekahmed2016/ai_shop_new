<?php

namespace App\Http\Requests;

use App\Enums\MerchantPermissions\PermissionKey;
use App\Support\MerchantAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MerchantBusinessProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(MerchantAuthorization::class)->can(PermissionKey::MerchantProfileUpdate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s\-()]+$/'],
            'merchant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'role' => ['prohibited'],
            'permissions' => ['prohibited'],
            'public_id' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('email') === '') {
            $this->merge(['email' => null]);
        }
    }
}
