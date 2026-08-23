<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $phone = $this->input('phone');

        $this->merge([
            'email' => is_string($email) ? strtolower(trim($email)) : $email,
            'phone' => is_string($phone) && trim($phone) === '' ? null : (is_string($phone) ? trim($phone) : $phone),
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'user_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
