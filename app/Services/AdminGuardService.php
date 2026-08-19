<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminGuardService
{
    public function adminUserCount(): int
    {
        return User::role('admin')->count();
    }

    public function isLastAdmin(User $user): bool
    {
        return $user->hasRole('admin') && $this->adminUserCount() === 1;
    }

    public function ensureCanChangeAdminRole(User $user, string $newRole): void
    {
        if ($newRole === 'admin' || ! $user->hasRole('admin')) {
            return;
        }

        if ($this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'Cannot remove the last administrator account.',
            ]);
        }
    }

    public function ensureCanDeleteUser(User $user): void
    {
        if ($this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the last administrator account.',
            ]);
        }
    }

    public function ensureCanDeleteRole(Role $role): void
    {
        if ($role->name === 'admin') {
            throw ValidationException::withMessages([
                'role' => 'The administrator role cannot be deleted.',
            ]);
        }
    }
}
