<?php

namespace App\Http\Requests;

use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerPortalClassificationRetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->customer?->isActive() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'additional_details' => ['nullable', 'string', 'max:5000'],
            'image' => SafeRasterImage::rules(required: false),
            'customer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'category_id' => ['prohibited'],
            'status' => ['prohibited'],
            'pending_request_id' => ['prohibited'],
            'customer_request_id' => ['prohibited'],
        ];
    }
}
