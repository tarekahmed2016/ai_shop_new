<?php

namespace App\Http\Requests;

use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MerchantOfferUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'availability_status' => ['required', new Enum(AvailabilityStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => SafeRasterImage::offerRules(),
            'remove_image_ids' => ['nullable', 'array', 'max:5'],
            'remove_image_ids.*' => ['integer'],
            'merchant_id' => ['prohibited'],
            'customer_request_id' => ['prohibited'],
            'request_match_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'currency' => ['prohibited'],
            'public_id' => ['prohibited'],
            'status' => ['prohibited'],
            'amount' => ['prohibited'],
            'balance' => ['prohibited'],
            'type' => ['prohibited'],
            'source' => ['prohibited'],
            'enforcement' => ['prohibited'],
            'offer_credits' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }

        if ($this->input('valid_until') === '') {
            $this->merge(['valid_until' => null]);
        }
    }
}
