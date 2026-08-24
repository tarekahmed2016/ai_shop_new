<?php

namespace App\Http\Requests;

use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerPortalRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->customer?->canUsePortal() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_text' => ['required', 'string', 'max:5000'],
            'image' => SafeRasterImage::rules(required: false),
            'category_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
            'daily_request_limit_override' => ['prohibited'],
        ];
    }
}
