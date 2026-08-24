<?php

namespace App\Http\Requests;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Models\Merchant;
use App\Services\MerchantOfferCreditService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantOfferCreditBulkAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkAddCredits', Merchant::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $service = app(MerchantOfferCreditService::class);

        return [
            'merchant_public_ids' => [
                'required',
                'array',
                'min:1',
                'max:'.$service->maxBulkMerchants(),
            ],
            'merchant_public_ids.*' => ['required', 'string', 'distinct', 'exists:merchants,public_id'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.$service->maxManualAmount()],
            'source' => ['required', Rule::enum(TransactionSource::class)->only(TransactionSource::manualChoices())],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('merchant_public_ids', []);
        if (is_array($ids)) {
            $this->merge([
                'merchant_public_ids' => array_values(array_unique(array_filter(
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
