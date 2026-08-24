<?php

namespace App\Http\Requests;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Models\Merchant;
use App\Services\MerchantOfferCreditService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantOfferCreditAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant instanceof Merchant
            && $this->user()?->can('addCredits', $merchant) === true;
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
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
            'merchant_id' => ['prohibited'],
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
