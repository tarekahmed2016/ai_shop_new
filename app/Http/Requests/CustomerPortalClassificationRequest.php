<?php

namespace App\Http\Requests;

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerPortalClassificationRequest extends FormRequest
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
            'request_text' => ['required', 'string', 'max:5000'],
            'additional_details' => ['nullable', 'string', 'max:5000'],
            'pending_request_id' => [
                'nullable',
                'string',
                Rule::exists('customer_requests', 'public_id')->where(
                    fn ($query) => $query->where('status', RequestStatus::PendingClassification->value)
                ),
            ],
            'image' => SafeRasterImage::rules(required: false),
            'customer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'category_id' => ['prohibited'],
            'status' => ['prohibited'],
            'customer_request_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('pending_request_id') === '') {
            $this->merge(['pending_request_id' => null]);
        }
    }
}
