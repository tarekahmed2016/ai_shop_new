<?php

namespace App\Http\Requests;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Models\User;
use App\Support\MerchantAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', new Enum(Role::class)],
            'status' => ['required', new Enum(Status::class)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:100'],
            // Never accept client merchant_id for authorization.
            'merchant_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = strtolower(trim((string) $this->input('email')));
            $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();

            if (! $existing) {
                if (blank($this->input('name'))) {
                    $validator->errors()->add('name', 'The name field is required when creating a new user.');
                }

                if (blank($this->input('password'))) {
                    $validator->errors()->add('password', 'The password field is required when creating a new user.');
                }
            }
        });
    }
}
