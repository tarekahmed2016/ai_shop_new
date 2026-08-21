<?php

namespace App\Support;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\MerchantUser;
use App\Services\MerchantPermissionService;

class MerchantAuthorization
{
    public function __construct(
        public MerchantContext $merchantContext,
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function requireActiveContext(): void
    {
        if (! $this->merchantContext->isActive()) {
            abort(403);
        }
    }

    public function currentMembership(): MerchantUser
    {
        $this->requireActiveContext();

        /** @var MerchantUser $membership */
        $membership = $this->merchantContext->membership();

        return $membership;
    }

    public function role(): Role
    {
        $role = $this->merchantContext->role();

        if ($role === null) {
            abort(403);
        }

        return $role;
    }

    public function can(string $permission): bool
    {
        return $this->merchantPermissionService->currentCan($permission);
    }

    public function canViewTeam(): bool
    {
        return $this->can(PermissionKey::TeamView->value);
    }

    public function canManageActivities(): bool
    {
        return $this->can(PermissionKey::ActivitiesManage->value);
    }

    public function canViewActivities(): bool
    {
        return $this->can(PermissionKey::ActivitiesView->value);
    }

    public function canCreateMembers(): bool
    {
        return $this->can(PermissionKey::TeamAddStaff->value)
            || $this->can(PermissionKey::TeamAddManager->value);
    }

    public function canAddRole(Role $role): bool
    {
        return match ($role) {
            Role::Staff => $this->can(PermissionKey::TeamAddStaff->value),
            Role::Manager => $this->can(PermissionKey::TeamAddManager->value) && $this->role() === Role::Owner,
            Role::Owner => false,
        };
    }

    /**
     * Roles the current actor may assign when creating/updating a membership.
     *
     * @return list<Role>
     */
    public function assignableRoles(): array
    {
        $roles = [];

        if ($this->canAddRole(Role::Staff) || $this->can(PermissionKey::TeamEditStaff->value)) {
            $roles[] = Role::Staff;
        }

        if ($this->role() === Role::Owner && (
            $this->canAddRole(Role::Manager) || $this->can(PermissionKey::TeamEditManager->value)
        )) {
            $roles[] = Role::Manager;
        }

        return $roles;
    }

    public function canManageMembership(MerchantUser $target): bool
    {
        return $this->canEditMembership($target) || $this->canRemoveMembership($target);
    }

    public function canEditMembership(MerchantUser $target): bool
    {
        if (! $this->merchantContext->isActive()) {
            return false;
        }

        if ($target->merchant_id !== $this->merchantContext->merchantId()) {
            return false;
        }

        $actorRole = $this->role();
        $targetRole = $target->role;

        if ($targetRole === Role::Owner) {
            return false;
        }

        if ($actorRole === Role::Owner) {
            return match ($targetRole) {
                Role::Manager => $this->can(PermissionKey::TeamEditManager->value),
                Role::Staff => $this->can(PermissionKey::TeamEditStaff->value),
                default => false,
            };
        }

        if ($actorRole === Role::Manager) {
            return $targetRole === Role::Staff && $this->can(PermissionKey::TeamEditStaff->value);
        }

        return false;
    }

    public function canRemoveMembership(MerchantUser $target): bool
    {
        if (! $this->merchantContext->isActive()) {
            return false;
        }

        if ($target->merchant_id !== $this->merchantContext->merchantId()) {
            return false;
        }

        $actorRole = $this->role();
        $targetRole = $target->role;

        if ($targetRole === Role::Owner) {
            return false;
        }

        if ($actorRole === Role::Owner) {
            return match ($targetRole) {
                Role::Manager => $this->can(PermissionKey::TeamRemoveManager->value),
                Role::Staff => $this->can(PermissionKey::TeamRemoveStaff->value),
                default => false,
            };
        }

        if ($actorRole === Role::Manager) {
            return $targetRole === Role::Staff && $this->can(PermissionKey::TeamRemoveStaff->value);
        }

        return false;
    }

    public function assertCanManageMembership(MerchantUser $target): void
    {
        if (! $this->canManageMembership($target)) {
            abort(403);
        }
    }

    public function assertCanEditMembership(MerchantUser $target): void
    {
        if (! $this->canEditMembership($target)) {
            abort(403);
        }
    }

    public function assertCanRemoveMembership(MerchantUser $target): void
    {
        if (! $this->canRemoveMembership($target)) {
            abort(403);
        }
    }

    public function assertMembershipInCurrentMerchant(MerchantUser $membership): void
    {
        $this->requireActiveContext();

        if ($membership->merchant_id !== $this->merchantContext->merchantId()) {
            abort(404);
        }
    }

    public function isLastActiveOwner(MerchantUser $membership): bool
    {
        if ($membership->role !== Role::Owner) {
            return false;
        }

        if ($membership->status !== MembershipStatus::Active) {
            return false;
        }

        $activeOwners = MerchantUser::query()
            ->where('merchant_id', $membership->merchant_id)
            ->where('role', Role::Owner)
            ->where('status', MembershipStatus::Active)
            ->count();

        return $activeOwners <= 1;
    }
}
