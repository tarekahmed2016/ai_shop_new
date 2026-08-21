<?php

namespace App\Http\Requests;

use App\Enums\Customers\Status;
use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class CustomerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer
            ? $this->user()?->can('update', $customer) === true
            : $this->user()?->can('create', Customer::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        $isUpdate = $customer instanceof Customer;

        if ($isUpdate) {
            return [
                'name' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
                'whatsapp_id' => [
                    'nullable',
                    'string',
                    'max:64',
                    Rule::unique('customers', 'whatsapp_id')->ignore($customer->id),
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('customers', 'email')->ignore($customer->id),
                ],
                'status' => ['required', new Enum(Status::class)],
                'password' => ['prohibited'],
                'password_confirmation' => ['prohibited'],
                'user_id' => ['prohibited'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', Rule::unique('customers', 'email')],
            'whatsapp_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('customers', 'whatsapp_id'),
            ],
            'status' => ['required', new Enum(Status::class)],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'user_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'whatsapp_id', 'email'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
