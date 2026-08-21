<?php

namespace App\Services;

use App\Enums\MerchantMemberships\Role;
use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MerchantMembershipService
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
        public MerchantContextService $merchantContextService,
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function getPaginatedMemberships(
        Merchant $merchant,
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'created_at', 'status'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return $merchant->memberships()
            ->with('user')
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Merchant $merchant, array $data): MerchantUser
    {
        $exists = MerchantUser::query()
            ->where('merchant_id', $merchant->id)
            ->where('user_id', $data['user_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'This user is already a member of this merchant.',
            ]);
        }

        $membership = $merchant->memberships()->create([
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        $this->merchantPermissionService->assignRoleDefaults($membership, onlyIfEmpty: true);

        $this->activityLogService->recordCreated(
            subject: $membership,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $merchant->name,
            metadata: [
                'merchant_public_id' => $merchant->public_id,
                'user_id' => $membership->user_id,
            ],
        );

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MerchantUser $membership, array $data): MerchantUser
    {
        $originalValues = $membership->only(self::ACTIVITY_FIELDS);

        $membership->update([
            'role' => $data['role'] ?? $membership->role,
            'status' => $data['status'] ?? $membership->status,
        ]);

        if (array_key_exists('role', $data)) {
            $originalRole = $originalValues['role'] instanceof Role
                ? $originalValues['role']->value
                : (string) $originalValues['role'];

            if ($originalRole !== $membership->role->value) {
                $membership->permissions()->detach();
                $this->merchantPermissionService->assignRoleDefaults($membership, onlyIfEmpty: true);
            }
        }

        $this->activityLogService->recordChanges(
            subject: $membership,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $membership->merchant?->name,
            metadata: [
                'merchant_public_id' => $membership->merchant?->public_id,
                'user_id' => $membership->user_id,
            ],
        );

        return $membership;
    }

    public function delete(MerchantUser $membership, Request $request): void
    {
        $this->activityLogService->recordDeleted(
            subject: $membership,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $membership->merchant?->name,
            metadata: [
                'merchant_public_id' => $membership->merchant?->public_id,
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
}
