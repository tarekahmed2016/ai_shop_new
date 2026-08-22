<?php

namespace App\Services;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name',
        'phone',
        'email',
        'status',
    ];

    /**
     * @var list<string>
     */
    private const OWNER_ACTIVITY_FIELDS = [
        'name',
        'email',
        'phone',
        'status',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public MerchantMembershipService $merchantMembershipService,
        public MerchantCategoryService $merchantCategoryService,
    ) {}

    public function getPaginatedMerchants(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'name', 'email', 'created_at', 'status'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return Merchant::query()
            ->withCount('memberships')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Merchant
    {
        return DB::transaction(function () use ($data) {
            $categoryPublicIds = array_values(array_unique($data['category_ids']));
            $ownerPassword = $data['password'];

            $merchant = new Merchant;
            $merchant->public_id = (string) Str::ulid();
            $merchant->fill(Arr::only($data, self::ACTIVITY_FIELDS));
            $merchant->save();

            $this->activityLogService->recordCreated(
                subject: $merchant,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $merchant->name,
            );

            $owner = new User;
            $owner->fill([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => $ownerPassword,
                'status' => UserStatus::Active,
            ]);
            $owner->save();

            $this->activityLogService->recordCreated(
                subject: $owner,
                allowedFields: self::OWNER_ACTIVITY_FIELDS,
                subjectLabel: $owner->name,
            );

            $this->merchantMembershipService->store($merchant, [
                'user_id' => $owner->id,
                'role' => Role::Owner,
                'status' => MembershipStatus::Active,
            ]);

            foreach ($categoryPublicIds as $categoryPublicId) {
                $this->merchantCategoryService->attach($merchant, $categoryPublicId);
            }

            return $merchant->fresh(['memberships', 'categories']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Merchant $merchant, array $data): Merchant
    {
        $originalValues = $merchant->only(self::ACTIVITY_FIELDS);

        $merchant->update(Arr::only($data, self::ACTIVITY_FIELDS));

        $this->activityLogService->recordChanges(
            subject: $merchant,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $merchant->name,
        );

        return $merchant;
    }

    /**
     * Merchant-workspace update of business contact fields only.
     * Never updates User records or merchant status.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBusinessProfile(Merchant $merchant, array $data): Merchant
    {
        unset(
            $data['id'],
            $data['public_id'],
            $data['status'],
            $data['merchant_id'],
            $data['user_id'],
            $data['role'],
            $data['permissions'],
        );

        return $this->update($merchant, Arr::only($data, ['name', 'phone', 'email']));
    }
}
