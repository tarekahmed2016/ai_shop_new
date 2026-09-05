<?php

namespace App\Http\Requests;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Models\Customer;
use App\Services\CustomerExtraRequestService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerExtraRequestDeductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && $this->user()?->can('manageLimits', $customer) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = app(CustomerExtraRequestService::class)->maxManualAmount();

        return [
            'amount' => ['required', 'integer', 'min:1', 'max:'.$max],
            'source' => ['required', Rule::enum(TransactionSource::class)->only(TransactionSource::manualChoices())],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:2000'],
            'paid_amount' => ['prohibited'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
            'customer_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('reference') === '') {
            $this->merge(['reference' => null]);
        }
    }
}
