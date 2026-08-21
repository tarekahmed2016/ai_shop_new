<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CustomerEnablePortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && $this->user()?->can('update', $customer) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'user_id' => ['prohibited'],
        ];
    }
}
