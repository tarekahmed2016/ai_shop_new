<?php

namespace App\Http\Requests;

use App\Models\CustomerRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecalculateMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customerRequest = $this->route('customerRequest');

        return $customerRequest instanceof CustomerRequest
            && $this->user()?->can('match', $customerRequest) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
