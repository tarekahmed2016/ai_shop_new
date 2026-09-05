<?php

namespace App\Http\Requests;

use App\Enums\Users\Status;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminPermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UserRequest extends FormRequest
{
    use Concerns\AuthorizesAdminPermission;

    public function authorize(): bool
    {
        if (! $this->authorizeAdminMutation('users.create', 'users.update', 'user')) {
            return false;
        }

        if (! $this->changesProtectedAdminRole()) {
            return true;
        }

        return AdminAccess::allows($this->user(), AdminPermissionCatalog::MANAGE_ADMIN_ROLE);
    }

    private function changesProtectedAdminRole(): bool
    {
        $target = $this->route('user');
        $currentlyAdmin = $target instanceof User && $target->hasRole('admin');
        $willBeAdmin = (string) $this->input('role') === 'admin';

        return $currentlyAdmin !== $willBeAdmin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['required', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', new Enum(Status::class)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
