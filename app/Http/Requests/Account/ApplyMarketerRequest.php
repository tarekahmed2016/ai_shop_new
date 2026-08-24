<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyMarketerRequest extends FormRequest
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
            'status' => ['prohibited'],
            'approved' => ['prohibited'],
            'is_active' => ['prohibited'],
            'marketer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'referral_code' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }
}
