<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    use Concerns\AuthorizesAdminPermission;

    public function authorize(): bool
    {
        return $this->authorizeAdminMutation('roles.create', 'roles.update', 'role');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
