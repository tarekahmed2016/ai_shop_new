<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WebPushUnsubscribeRequest extends FormRequest
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
            'endpoint' => ['required', 'string', 'url', 'max:1024'],
            'merchant_id' => ['prohibited'],
            'merchant_public_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
        ];
    }
}
