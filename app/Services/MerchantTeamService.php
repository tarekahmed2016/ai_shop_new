<?php

namespace App\Services;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Support\MerchantAuthorization;
use App\Support\MerchantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantTeamService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'merchant_id',
        'user_id',
        'role',
        'status',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public MerchantContext $merchantContext,
        public MerchantAuthorization $merchantAuthorization,
        public MerchantContextService $merchantContextService,
        public MerchantMembershipService $merchantMembershipService,
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function currentMerchant(): Merchant
    {
        $this->merchantAuthorization->requireActiveContext();

        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        return $merchant;
    }

    public function getPaginatedMembers(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $merchant = $this->currentMerchant();

        $paginator = $this->merchantMembershipService->getPaginatedMemberships(
            merchant: $merchant,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
            perPage: $perPage,
        );

        $actor = $this->merchantContext->membership();

        $paginator->getCollection()->transform(function (MerchantUser $membership) use ($actor) {
            $isSelfOwner = $actor && $membership->id === $actor->id && $membership->role === Role::Owner;
            $isProtectedOwner = $this->merchantAuthorization->isLastActiveOwner($membership) || $isSelfOwner;

            $canEdit = $this->merchantAuthorization->canEditMembership($membership) && ! $isProtectedOwner;
            $canRemove = $this->merchantAuthorization->canRemoveMembership($membership) && ! $isProtectedOwner;

            $membership->loadMissing('permissions');
            $membership->setAttribute('can_manage', $canEdit || $canRemove);
            $membership->setAttribute('can_edit', $canEdit);
            $membership->setAttribute('can_remove', $canRemove);
            $membership->setAttribute('is_protected_owner', $isProtectedOwner);
            $membership->setAttribute('is_full_access', $membership->role === Role::Owner);
            $membership->setAttribute(
                'permission_keys',
                $this->merchantPermissionService->permissionKeysFor($membership)
            );
            $membership->setAttribute(
                'can_manage_permissions',
                $actor ? $this->merchantPermissionService->canManageTargetPermissions($actor, $membership) : false
            );

            return $membership;
        });

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMember(array $data): MerchantUser
    {
        $merchant = $this->currentMerchant();
        $actor = $this->merchantAuthorization->currentMembership();

        $role = Role::from((string) $data['role']);

        if (! $this->merchantAuthorization->canAddRole($role)) {
            abort(403);
        }

        $this->assertAssignableRole($role);

        $status = MembershipStatus::from((int) $data['status']);
        $email = strtolower(trim((string) $data['email']));

        return DB::transaction(function () use ($merchant, $data, $role, $status, $email, $actor) {
            $existingUser = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($existingUser) {
                $user = $existingUser;
            } else {
                $user = new User;
                $user->name = (string) $data['name'];
                $user->email = $email;
                $user->phone = $data['phone'] ?? null;
                $user->password = (string) $data['password'];
                $user->status = UserStatus::Active;
                $user->save();
            }

            $duplicate = MerchantUser::query()
                ->where('merchant_id', $merchant->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'email' => 'This user is already a member of this merchant.',
                ]);
            }

            $membership = $merchant->memberships()->create([
                'user_id' => $user->id,
                'role' => $role,
                'status' => $status,
            ]);

            $this->applyPermissionsOnWrite($actor, $membership, $data['permissions'] ?? null, isCreate: true);

            $this->activityLogService->recordCreated(
                subject: $membership,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $merchant->name,
                metadata: [
                    'action' => 'merchant.member.added',
                    'merchant_public_id' => $merchant->public_id,
                    'user_id' => $user->id,
                    'created_user' => $existingUser === null,
                ],
            );

            return $membership->load(['user', 'permissions']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMember(MerchantUser $membership, array $data): MerchantUser
    {
        $this->merchantAuthorization->assertMembershipInCurrentMerchant($membership);
        $this->merchantAuthorization->assertCanEditMembership($membership);

        $actor = $this->merchantAuthorization->currentMembership();
        $role = Role::from((string) $data['role']);
        $status = MembershipStatus::from((int) $data['status']);

        $this->assertAssignableRole($role);
        $this->assertOwnerProtection($membership, $role, $status);
        $this->assertNotSelfOwnerMutation($membership);

        // Role change may require matching add permission for the new role.
        if ($role !== $membership->role && ! $this->merchantAuthorization->canAddRole($role)
            && ! ($role === Role::Staff && $this->merchantAuthorization->canEditMembership($membership))) {
            // Allow owner/manager to keep editing within editable roles already assigned.
            if (! in_array($role, $this->merchantAuthorization->assignableRoles(), true)) {
                abort(403);
            }
        }

        $originalValues = $membership->only(self::ACTIVITY_FIELDS);
        $wasActive = $membership->status === MembershipStatus::Active;
        $previousRole = $membership->role;

        $membership->role = $role;
        $membership->status = $status;
        $membership->save();

        if ($previousRole !== $role && ! array_key_exists('permissions', $data)) {
            $membership->permissions()->detach();
            $this->merchantPermissionService->assignRoleDefaults($membership, onlyIfEmpty: true);
        }

        if (array_key_exists('permissions', $data)) {
            $this->applyPermissionsOnWrite($actor, $membership, $data['permissions'], isCreate: false);
        }

        $action = 'merchant.member.role_changed';
        if ($previousRole === $role) {
            if (! $wasActive && $status === MembershipStatus::Active) {
                $action = 'merchant.member.activated';
            } elseif ($wasActive && $status === MembershipStatus::Inactive) {
                $action = 'merchant.member.deactivated';
            }
        }

        $this->activityLogService->recordChanges(
            subject: $membership,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $membership->merchant?->name,
            metadata: [
                'action' => $action,
                'merchant_public_id' => $this->merchantContext->publicId(),
                'user_id' => $membership->user_id,
            ],
        );

        return $membership->fresh(['user', 'permissions']);
    }

    public function removeMember(MerchantUser $membership, Request $request): void
    {
        $this->merchantAuthorization->assertMembershipInCurrentMerchant($membership);
        $this->merchantAuthorization->assertCanRemoveMembership($membership);

        if ($this->merchantAuthorization->isLastActiveOwner($membership)) {
            throw ValidationException::withMessages([
                'membership' => 'The last active merchant owner cannot be removed.',
            ]);
        }

        $this->assertNotSelfOwnerMutation($membership);

        $this->activityLogService->recordDeleted(
            subject: $membership,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $membership->merchant?->name,
            metadata: [
                'action' => 'merchant.member.removed',
                'merchant_public_id' => $this->merchantContext->publicId(),
                'user_id' => $membership->user_id,
            ],
        );

        $userId = $membership->user_id;
        $merchantId = $membership->merchant_id;
        $membership->delete();

        $activeId = $request->session()->get(MerchantContextService::SESSION_KEY);
        if ((int) $activeId === (int) $merchantId && (int) $request->user()?->id === (int) $userId) {
            $this->merchantContextService->clear($request);
        }
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
     */
    public function assignableRolesForUi(): array
    {
        return array_map(
            fn (Role $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'name' => $role->name,
            ],
            $this->merchantAuthorization->assignableRoles()
        );
    }

    /**
     * @param  list<string>|null  $permissions
     */
    private function applyPermissionsOnWrite(
        MerchantUser $actor,
        MerchantUser $membership,
        ?array $permissions,
        bool $isCreate,
    ): void {
        if ($permissions === null) {
            $this->merchantPermissionService->assignRoleDefaults($membership, onlyIfEmpty: true);

            return;
        }

        if (! $this->merchantPermissionService->canManageTargetPermissions($actor, $membership)) {
            if ($isCreate) {
                $this->merchantPermissionService->assignRoleDefaults($membership, onlyIfEmpty: true);

                return;
            }

            throw ValidationException::withMessages([
                'permissions' => 'You are not allowed to manage permissions for this member.',
            ]);
        }

        $filtered = $this->merchantPermissionService->filterAssignableKeys(
            $actor,
            $membership->role,
            $permissions,
        );

        $this->merchantPermissionService->syncPermissions(
            membership: $membership,
            permissionKeys: $filtered,
            actor: $actor,
        );
    }

    private function assertAssignableRole(Role $role): void
    {
        $allowed = $this->merchantAuthorization->assignableRoles();

        foreach ($allowed as $allowedRole) {
            if ($allowedRole === $role) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'role' => 'You are not allowed to assign this merchant role.',
        ]);
    }

    private function assertOwnerProtection(MerchantUser $membership, Role $newRole, MembershipStatus $newStatus): void
    {
        if (! $this->merchantAuthorization->isLastActiveOwner($membership)) {
            return;
        }

        if ($newRole !== Role::Owner || $newStatus !== MembershipStatus::Active) {
            throw ValidationException::withMessages([
                'role' => 'The last active merchant owner cannot be demoted or deactivated.',
            ]);
        }
    }

    private function assertNotSelfOwnerMutation(MerchantUser $membership): void
    {
        $actor = $this->merchantContext->membership();

        if ($actor && $actor->id === $membership->id && $membership->role === Role::Owner) {
            throw ValidationException::withMessages([
                'membership' => 'You cannot modify or remove your own owner membership.',
            ]);
        }
    }
}
