<?php

namespace App\Http\Requests;

use App\Enums\Payments\Method;
use App\Models\Marketer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketerPayoutStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $marketer = $this->route('marketer');

        return $marketer instanceof Marketer
            && $this->user()?->can('recordPayout', $marketer) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'payment_method' => ['required', Rule::enum(Method::class)->only(Method::manualChoices())],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('reference') === '') {
            $this->merge(['reference' => null]);
        }

        if ($this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }
}
