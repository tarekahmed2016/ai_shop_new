<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerDailyRequestLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = max(1, (int) config('customer_requests.max_daily_limit', 100));

        return [
            'daily_limit' => ['required', 'integer', 'min:1', 'max:'.$max],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
