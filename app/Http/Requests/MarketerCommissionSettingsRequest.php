<?php

namespace App\Http\Requests;

use App\Models\Marketer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarketerCommissionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCommissionSettings', Marketer::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_commission_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
            'merchant_commission_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }
}
