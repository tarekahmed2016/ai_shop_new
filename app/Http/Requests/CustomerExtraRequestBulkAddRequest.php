<?php

namespace App\Http\Requests;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Models\Customer;
use App\Services\CustomerExtraRequestService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerExtraRequestBulkAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageLimits', Customer::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $service = app(CustomerExtraRequestService::class);

        return [
            'customer_public_ids' => [
                'required',
                'array',
                'min:1',
                'max:'.$service->maxBulkCustomers(),
            ],
            'customer_public_ids.*' => ['required', 'string', 'distinct', 'exists:customers,public_id'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.$service->maxManualAmount()],
            'source' => ['required', Rule::enum(TransactionSource::class)->only(TransactionSource::manualChoices())],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
            'payer_user_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('customer_public_ids', []);
        if (is_array($ids)) {
            $this->merge([
                'customer_public_ids' => array_values(array_unique(array_filter(
                    array_map(fn ($id) => is_string($id) ? trim($id) : (string) $id, $ids),
                    fn (string $id) => $id !== '',
                ))),
            ]);
        }

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
