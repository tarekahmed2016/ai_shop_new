<?php

namespace App\Http\Requests;

use App\Support\MerchantAuthorization;
use App\Support\WhatsAppPhoneValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MerchantBusinessActivityWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(MerchantAuthorization::class)->canManageActivities();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'whatsapp_phone' => WhatsAppPhoneValidation::rules(required: true),
            'merchant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'category_id' => ['prohibited'],
            'status' => ['prohibited'],
            'id' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => WhatsAppPhoneValidation::after($validator));
    }
}
