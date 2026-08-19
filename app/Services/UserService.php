<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name',
        'email',
        'phone',
        'status',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public AdminGuardService $adminGuardService,
    ) {}

    /**
     * @param  array<int, string>  $withRelation
     */
    public function getPaginatedUsers(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15,
        array $withRelation = ['roles']
    ): LengthAwarePaginator {
        return User::query()
            ->with($withRelation)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): User
    {
        $role = $data['role'];
        unset($data['role']);

        $user = User::create($data);
        $user->assignRole($role);

        $this->activityLogService->recordCreated(
            subject: $user,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $user->name,
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $role = $data['role'];
        unset($data['role']);

        $this->adminGuardService->ensureCanChangeAdminRole(user: $user, newRole: $role);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $originalValues = $user->only(self::ACTIVITY_FIELDS);

        $user->update($data);
        $user->syncRoles([$role]);

        $this->activityLogService->recordChanges(
            subject: $user,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $user->name,
        );

        return $user;
    }

    public function delete(User $user): void
    {
        $this->adminGuardService->ensureCanDeleteUser(user: $user);

        $this->activityLogService->recordDeleted(
            subject: $user,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $user->name,
        );

        $user->delete();
    }
}
