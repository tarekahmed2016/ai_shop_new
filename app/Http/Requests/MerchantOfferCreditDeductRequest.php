<?php

namespace App\Http\Requests;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Models\Merchant;
use App\Services\MerchantOfferCreditService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantOfferCreditDeductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant instanceof Merchant
            && $this->user()?->can('deductCredits', $merchant) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = app(MerchantOfferCreditService::class)->maxManualAmount();

        return [
            'amount' => ['required', 'integer', 'min:1', 'max:'.$max],
            'source' => ['required', Rule::enum(TransactionSource::class)->only(TransactionSource::manualChoices())],
            'notes' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'paid_amount' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('reference') === '') {
            $this->merge(['reference' => null]);
        }
    }
}
