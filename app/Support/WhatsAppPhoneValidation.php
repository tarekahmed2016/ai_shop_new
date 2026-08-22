<?php

namespace App\Support;

use Illuminate\Validation\Validator;

class WhatsAppPhoneValidation
{
    /**
     * @return list<string>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            'regex:/^[0-9+\s\-()]+$/',
        ];
    }

    public static function after(Validator $validator, string $attribute = 'whatsapp_phone'): void
    {
        $value = $validator->getValue($attribute);

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (! WhatsAppLink::isValid($value)) {
            $validator->errors()->add($attribute, 'The WhatsApp number is invalid.');
        }
    }
}
