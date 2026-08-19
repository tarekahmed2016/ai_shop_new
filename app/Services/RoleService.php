<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class RoleService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name',
        'guard_name',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public AdminGuardService $adminGuardService,
    ) {}

    public function getPaginatedRoles(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return Role::query()
            ->with('permissions')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        $metadata = $this->permissionMetadata(
            oldPermissionIds: [],
            newPermissionIds: $role->permissions()->pluck('permissions.id')->sort()->values()->all(),
        );

        $this->activityLogService->recordCreated(
            subject: $role,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $role->name,
            metadata: $metadata,
        );

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $originalValues = $role->only(self::ACTIVITY_FIELDS);
        $originalPermissionIds = $role->permissions()->pluck('permissions.id')->sort()->values()->all();

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        $metadata = $this->permissionMetadata(
            oldPermissionIds: $originalPermissionIds,
            newPermissionIds: $role->permissions()->pluck('permissions.id')->sort()->values()->all(),
        );

        $this->activityLogService->recordChanges(
            subject: $role,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $role->name,
            metadata: $metadata,
        );

        return $role;
    }

    public function delete(Role $role): void
    {
        $this->adminGuardService->ensureCanDeleteRole(role: $role);

        $metadata = $this->permissionMetadata(
            oldPermissionIds: $role->permissions()->pluck('permissions.id')->sort()->values()->all(),
            newPermissionIds: [],
        );

        $this->activityLogService->recordDeleted(
            subject: $role,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $role->name,
            metadata: $metadata,
        );

        $role->delete();
    }

    /**
     * @param  list<int>  $oldPermissionIds
     * @param  list<int>  $newPermissionIds
     * @return array<string, mixed>
     */
    private function permissionMetadata(array $oldPermissionIds, array $newPermissionIds): array
    {
        if ($oldPermissionIds === $newPermissionIds) {
            return [];
        }

        return [
            'permissions' => [
                'old' => $oldPermissionIds,
                'new' => $newPermissionIds,
            ],
        ];
    }
}
