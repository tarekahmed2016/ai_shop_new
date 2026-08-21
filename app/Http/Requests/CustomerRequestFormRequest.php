<?php

namespace App\Http\Requests;

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\Status;
use App\Models\CustomerRequest;
use App\Support\SafeRasterImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CustomerRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customerRequest = $this->route('customerRequest');

        return $customerRequest
            ? $this->user()?->can('update', $customerRequest) === true
            : $this->user()?->can('create', CustomerRequest::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string', Rule::exists('customers', 'public_id')],
            'request_text' => ['required', 'string', 'max:5000'],
            'status' => ['required', new Enum(Status::class)],
            'category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'public_id')->where(
                    fn ($query) => $query->where('status', CategoryStatus::Active->value)
                ),
            ],
            'image' => SafeRasterImage::rules(required: false),
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('category_id') === '') {
            $this->merge(['category_id' => null]);
        }
    }
}
