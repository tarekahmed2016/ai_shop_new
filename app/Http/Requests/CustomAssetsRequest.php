<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomAssetsRequest extends FormRequest
{
    use Concerns\AuthorizesAdminPermission;

    public function authorize(): bool
    {
        return $this->authorizeAdmin('settings.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'custom_js' => ['nullable', 'string', 'max:50000'],
        ];
    }
}
