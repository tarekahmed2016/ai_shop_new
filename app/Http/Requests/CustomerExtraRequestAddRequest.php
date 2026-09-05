<?php

namespace App\Http\Requests;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Models\Customer;
use App\Services\CustomerExtraRequestService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerExtraRequestAddRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'payer_user_id' => ['prohibited'],
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

        if ($this->exists('paid_amount') && $this->input('paid_amount') === '') {
            $this->merge(['paid_amount' => null]);
        }
    }
}
