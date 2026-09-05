<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status as CategoryStatus;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerPortalClassificationConfirmRequest extends FormRequest
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
            'category_id' => [
                'required',
                'string',
                Rule::exists('categories', 'public_id')->where(
                    fn ($query) => $query->where('status', CategoryStatus::Active->value)
                ),
            ],
            'submission_token' => CustomerRequestPipelineConfig::submissionTokenRules(),
            'customer_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'merchant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'customer_request_id' => ['prohibited'],
            'daily_request_limit_override' => ['prohibited'],
        ];
    }
}
