<?php

namespace App\Services;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * Create a Merchant owned by an existing User. Never creates or mutates the User.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForUser(User $owner, array $data): Merchant
    {
        $categoryPublicIds = array_values(array_unique($data['category_ids'] ?? []));

        if ($categoryPublicIds === []) {
            throw ValidationException::withMessages([
                'category_ids' => 'Select at least one business category.',
            ]);
        }

        if (! isset($data['name']) || trim((string) $data['name']) === '') {
            throw ValidationException::withMessages([
                'name' => 'The name field is required.',
            ]);
        }

        return DB::transaction(function () use ($owner, $data, $categoryPublicIds) {
            $merchant = new Merchant;
            $merchant->public_id = (string) Str::ulid();
            $merchant->fill(Arr::only($data, self::ACTIVITY_FIELDS));
            if (! array_key_exists('status', $data) || $data['status'] === null) {
                $merchant->status = MerchantStatus::Active;
            }
            $merchant->save();

            $this->activityLogService->recordCreated(
                subject: $merchant,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $merchant->name,
                metadata: [
                    'action' => 'merchant.created_for_existing_user',
                    'owner_user_id' => $owner->id,
                ],
                actor: $owner,
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
