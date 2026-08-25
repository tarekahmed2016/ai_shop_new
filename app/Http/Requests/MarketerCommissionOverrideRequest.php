<?php

namespace App\Http\Requests;

use App\Models\Marketer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarketerCommissionOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $marketer = $this->route('marketer');

        return $marketer instanceof Marketer
            && $this->user()?->can('updateCommissionRates', $marketer) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
            'merchant_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['customer_commission_rate', 'merchant_commission_rate'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
