<?php

namespace App\Services;

use App\Enums\ActivityLogs\Event;
use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\Merchant;
use App\Models\MerchantPermission;
use App\Models\MerchantUser;
use App\Models\User;
use App\Support\MerchantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantPermissionService
{
    /**
     * @var array<int, list<string>>
     */
    private array $permissionCache = [];

    public function __construct(
        public MerchantContext $merchantContext,
        public ActivityLogService $activityLogService,
    ) {}

    public function seedCatalog(): void
    {
        $now = now();

        foreach (PermissionKey::cases() as $permission) {
            MerchantPermission::query()->updateOrCreate(
                ['key' => $permission->value],
                [
                    'name_ar' => $permission->nameAr(),
                    'name_en' => $permission->nameEn(),
                    'group_key' => $permission->groupKey(),
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Idempotent default assignment for a membership that has no custom rows yet,
     * or force-fill missing defaults without removing customized extras.
     * When $onlyIfEmpty is true, skip memberships that already have any permissions.
     */
    public function assignRoleDefaults(MerchantUser $membership, bool $onlyIfEmpty = true): void
    {
        $this->seedCatalog();

        if ($onlyIfEmpty && $membership->permissionAssignments()->exists()) {
            return;
        }

        $keys = $this->defaultKeysForRole($membership->role);
        $permissionIds = MerchantPermission::query()
            ->whereIn('key', array_map(fn (PermissionKey $key) => $key->value, $keys))
            ->pluck('id', 'key');

        foreach ($keys as $key) {
            $permissionId = $permissionIds[$key->value] ?? null;
            if ($permissionId === null) {
                continue;
            }

            $membership->permissionAssignments()->firstOrCreate([
                'merchant_permission_id' => $permissionId,
            ]);
        }

        unset($this->permissionCache[$membership->id]);
    }

    /**
     * Backfill defaults for all memberships missing permissions (migration/repair).
     * Does not overwrite existing customized permission sets.
     */
    public function backfillMissingDefaults(): int
    {
        $this->seedCatalog();
        $updated = 0;

        MerchantUser::query()->orderBy('id')->chunkById(100, function ($memberships) use (&$updated): void {
            foreach ($memberships as $membership) {
                if ($membership->permissionAssignments()->exists()) {
                    continue;
                }

                $this->assignRoleDefaults($membership, onlyIfEmpty: true);
                $updated++;
            }
        });

        return $updated;
    }

    public function can(?User $user, ?Merchant $merchant, string $permission): bool
    {
        if ($user === null || $merchant === null) {
            return false;
        }

        if (! $this->merchantContext->isActive()) {
            return false;
        }

        if ($this->merchantContext->merchantId() !== $merchant->id) {
            return false;
        }

        if ($this->merchantContext->membership()?->user_id !== $user->id) {
            return false;
        }

        return $this->membershipCan($this->merchantContext->membership(), $permission);
    }

    public function currentCan(string $permission): bool
    {
        if (! $this->merchantContext->isActive()) {
            return false;
        }

        return $this->membershipCan($this->merchantContext->membership(), $permission);
    }

    public function membershipCan(?MerchantUser $membership, string $permission): bool
    {
        if ($membership === null) {
            return false;
        }

        if (! $membership->isActive()) {
            return false;
        }

        $membership->loadMissing('merchant');

        if ($membership->merchant === null || ! $membership->merchant->isActive()) {
            return false;
        }

        // Owner hard shortcut: full merchant access cannot be stripped via permission rows.
        if ($membership->role === Role::Owner) {
            return PermissionKey::tryFrom($permission) !== null;
        }

        return in_array($permission, $this->permissionKeysFor($membership), true);
    }

    /**
     * @return list<string>
     */
    public function permissionKeysFor(MerchantUser $membership): array
    {
        if (isset($this->permissionCache[$membership->id])) {
            return $this->permissionCache[$membership->id];
        }

        if ($membership->role === Role::Owner) {
            $keys = array_map(fn (PermissionKey $key) => $key->value, PermissionKey::ownerDefaults());
            $this->permissionCache[$membership->id] = $keys;

            return $keys;
        }

        $keys = $membership->permissions()
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->values()
            ->all();

        $this->permissionCache[$membership->id] = $keys;

        return $keys;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    public function syncPermissions(
        MerchantUser $membership,
        array $permissionKeys,
        ?MerchantUser $actor = null,
        bool $log = true,
    ): void {
        if ($membership->role === Role::Owner) {
            // Owners always retain full access; persist catalog for admin visibility.
            $permissionKeys = array_map(fn (PermissionKey $key) => $key->value, PermissionKey::ownerDefaults());
        }

        $normalized = $this->normalizePermissionKeys($permissionKeys);
        $this->assertKeysAllowedForTargetRole($membership->role, $normalized);

        $permissionIds = MerchantPermission::query()
            ->whereIn('key', $normalized)
            ->pluck('id', 'key');

        if ($permissionIds->count() !== count($normalized)) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permission keys are invalid.',
            ]);
        }

        $before = $membership->permissions()->pluck('key')->sort()->values()->all();
        $after = collect($normalized)->sort()->values()->all();

        DB::transaction(function () use ($membership, $permissionIds): void {
            $membership->permissions()->sync($permissionIds->values()->all());
        });

        unset($this->permissionCache[$membership->id]);

        if ($log && $before !== $after) {
            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            $this->activityLogService->recordSystem(
                subject: $membership->fresh() ?? $membership,
                event: Event::Updated,
                oldValues: ['permissions' => $before],
                newValues: ['permissions' => $after],
                allowedFields: ['permissions'],
                subjectLabel: $membership->merchant?->name,
                metadata: [
                    'action' => 'merchant.member.permissions_updated',
                    'merchant_public_id' => $membership->merchant?->public_id,
                    'user_id' => $membership->user_id,
                    'actor_membership_id' => $actor?->id,
                    'permissions_added' => $added,
                    'permissions_removed' => $removed,
                ],
            );
        }
    }

    /**
     * Permissions the current actor may assign when creating/editing a membership.
     *
     * @return list<string>
     */
    public function assignablePermissionKeysForActor(?MerchantUser $actor, Role $targetRole): array
    {
        if ($actor === null || ! $actor->isActive()) {
            return [];
        }

        if ($targetRole === Role::Owner) {
            return [];
        }

        if ($actor->role === Role::Owner) {
            return match ($targetRole) {
                Role::Manager => array_map(fn (PermissionKey $key) => $key->value, PermissionKey::allKeys()),
                Role::Staff => array_values(array_diff(
                    array_map(fn (PermissionKey $key) => $key->value, PermissionKey::allKeys()),
                    array_map(fn (PermissionKey $key) => $key->value, PermissionKey::managerLevelKeys()),
                )),
                default => [],
            };
        }

        if ($actor->role === Role::Manager) {
            if ($targetRole !== Role::Staff) {
                return [];
            }

            if (! $this->membershipCan($actor, PermissionKey::TeamManagePermissions->value)) {
                return [];
            }

            $managerKeys = $this->permissionKeysFor($actor);
            $managerLevel = array_map(fn (PermissionKey $key) => $key->value, PermissionKey::managerLevelKeys());

            return array_values(array_filter(
                $managerKeys,
                fn (string $key) => ! in_array($key, $managerLevel, true)
            ));
        }

        return [];
    }

    /**
     * Whether the actor may customize permission checkboxes for the target.
     */
    public function canManageTargetPermissions(MerchantUser $actor, MerchantUser $target): bool
    {
        if ($actor->merchant_id !== $target->merchant_id) {
            return false;
        }

        if ($target->role === Role::Owner) {
            return false;
        }

        if ($actor->role === Role::Owner) {
            return $target->role === Role::Manager || $target->role === Role::Staff;
        }

        if ($actor->role === Role::Manager) {
            return $target->role === Role::Staff
                && $this->membershipCan($actor, PermissionKey::TeamManagePermissions->value);
        }

        return false;
    }

    /**
     * Validate requested keys against actor delegation boundary.
     *
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    public function filterAssignableKeys(MerchantUser $actor, Role $targetRole, array $requestedKeys): array
    {
        $allowed = $this->assignablePermissionKeysForActor($actor, $targetRole);
        $normalized = $this->normalizePermissionKeys($requestedKeys);

        $invalid = array_values(array_diff($normalized, $allowed));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'You are not allowed to assign one or more of the selected permissions.',
            ]);
        }

        return $normalized;
    }

    /**
     * @return list<array{group_key: string, group_label_en: string, group_label_ar: string, permissions: list<array{key: string, name_en: string, name_ar: string}>}>
     */
    public function catalogGroupedForUi(): array
    {
        $this->seedCatalog();

        /** @var Collection<string, Collection<int, MerchantPermission>> $grouped */
        $grouped = MerchantPermission::query()
            ->orderBy('group_key')
            ->orderBy('id')
            ->get()
            ->groupBy('group_key');

        $labels = [
            'requests' => ['en' => 'Requests', 'ar' => 'الطلبات'],
            'activities' => ['en' => 'Business Activities', 'ar' => 'الأنشطة التجارية'],
            'team' => ['en' => 'Team', 'ar' => 'الفريق'],
            'merchant_profile' => ['en' => 'Merchant Profile', 'ar' => 'ملف التاجر'],
            'offers' => ['en' => 'Offers', 'ar' => 'العروض'],
        ];

        $result = [];

        foreach ($grouped as $groupKey => $permissions) {
            $result[] = [
                'group_key' => $groupKey,
                'group_label_en' => $labels[$groupKey]['en'] ?? $groupKey,
                'group_label_ar' => $labels[$groupKey]['ar'] ?? $groupKey,
                'permissions' => $permissions->map(fn (MerchantPermission $permission) => [
                    'key' => $permission->key,
                    'name_en' => $permission->name_en,
                    'name_ar' => $permission->name_ar,
                ])->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * @return list<PermissionKey>
     */
    public function defaultKeysForRole(Role $role): array
    {
        return match ($role) {
            Role::Owner => PermissionKey::ownerDefaults(),
            Role::Manager => PermissionKey::managerDefaults(),
            Role::Staff => PermissionKey::staffDefaults(),
        };
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function normalizePermissionKeys(array $keys): array
    {
        $normalized = [];

        foreach ($keys as $key) {
            $enum = PermissionKey::tryFrom((string) $key);
            if ($enum === null) {
                throw ValidationException::withMessages([
                    'permissions' => 'Invalid merchant permission key.',
                ]);
            }
            $normalized[] = $enum->value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $keys
     */
    private function assertKeysAllowedForTargetRole(Role $role, array $keys): void
    {
        if ($role === Role::Owner) {
            return;
        }

        $managerLevel = array_map(fn (PermissionKey $key) => $key->value, PermissionKey::managerLevelKeys());

        if ($role === Role::Staff) {
            $forbidden = array_values(array_intersect($keys, $managerLevel));
            if ($forbidden !== []) {
                throw ValidationException::withMessages([
                    'permissions' => 'Staff cannot receive manager-level permissions.',
                ]);
            }
        }
    }

    public function clearCache(): void
    {
        $this->permissionCache = [];
    }
}
