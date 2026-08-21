<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status as CategoryStatus;
use App\Support\MerchantAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantBusinessActivityRequest extends FormRequest
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
            'category_id' => [
                'required',
                'string',
                Rule::exists('categories', 'public_id')->where(
                    fn ($query) => $query->where('status', CategoryStatus::Active->value)
                ),
            ],
            'merchant_id' => ['prohibited'],
        ];
    }
}
