<?php

namespace App\Http\Requests;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Support\MerchantAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class MerchantTeamStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(MerchantAuthorization::class)->canCreateMembers();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', new Enum(Role::class)],
            'status' => ['required', new Enum(Status::class)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:100'],
            // Never accept client merchant_id for authorization.
            'merchant_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
