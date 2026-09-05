<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminPermissionCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminGuardService
{
    public function adminUserCount(): int
    {
        return User::role('admin')->count();
    }

    /**
     * Serialize last-admin checks against concurrent demotions/deletes.
     *
     * The first statement must be a locking read. A non-locking
     * User::role('admin')->pluck() would freeze a REPEATABLE READ snapshot
     * of "two admins" before waiting on user row locks, allowing both
     * racers to demote.
     */
    public function lockUsersForAdminGuard(User $target): void
    {
        $adminRole = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($adminRole === null) {
            User::query()->whereKey($target->id)->lockForUpdate()->first();

            return;
        }

        $modelType = (new User)->getMorphClass();

        DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_type', $modelType)
            ->orderBy('model_id')
            ->lockForUpdate()
            ->get();

        $ids = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_type', $modelType)
            ->pluck('model_id')
            ->map(fn ($id) => (int) $id)
            ->push((int) $target->id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    public function isLastAdmin(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        $adminRole = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($adminRole === null) {
            return false;
        }

        $others = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', '!=', $user->id)
            ->lockForUpdate()
            ->count();

        return $others === 0;
    }

    public function ensureCanAssignOrRemoveAdminRole(?User $actor, bool $targetIsCurrentlyAdmin, string $newRole): void
    {
        $willBeAdmin = $newRole === 'admin';

        if ($targetIsCurrentlyAdmin === $willBeAdmin) {
            return;
        }

        if (! AdminAccess::allows($actor, AdminPermissionCatalog::MANAGE_ADMIN_ROLE)) {
            throw new AuthorizationException;
        }
    }

    public function ensureCanChangeAdminRole(User $user, string $newRole, ?User $actor = null): void
    {
        $this->ensureCanAssignOrRemoveAdminRole(
            $actor ?? request()->user(),
            $user->hasRole('admin'),
            $newRole,
        );

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

        if ($user->marketer()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete a user with a marketer profile. Doing so would destroy their referral and commission history. Deactivate the marketer instead.',
            ]);
        }

        if ($user->customer()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete a user with a customer profile. Suspend the customer instead of deleting the account.',
            ]);
        }

        if ($user->merchantMemberships()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete a user with merchant team membership(s). Remove their merchant membership(s) first, or deactivate them instead.',
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
