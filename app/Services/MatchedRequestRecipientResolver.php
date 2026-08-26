<?php

namespace App\Services;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\MerchantUser;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Support\Collection;

class MatchedRequestRecipientResolver
{
    public function __construct(
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function chunkSize(): int
    {
        return max(1, min(500, (int) config('notifications.matched_request_chunk_size', 200)));
    }

    /**
     * @return Collection<int, User>
     */
    public function usersFor(RequestMatch $match): Collection
    {
        $merchantId = (int) $match->merchant_id;
        if ($merchantId < 1) {
            return collect();
        }

        return $this->usersGroupedByMerchantId([$merchantId])->get($merchantId, collect())->values();
    }

    /**
     * @param  list<int>  $matchIds
     * @param  callable(RequestMatch $match, Collection<int, User> $users): void  $callback
     */
    public function eachMatchRecipients(array $matchIds, callable $callback, ?int $customerRequestId = null): void
    {
        $ids = array_values(array_unique(array_filter($matchIds, fn ($id) => is_int($id) && $id > 0)));
        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, $this->chunkSize()) as $idChunk) {
            $matches = RequestMatch::query()
                ->when($customerRequestId !== null && $customerRequestId > 0, function ($query) use ($customerRequestId) {
                    $query->where('customer_request_id', $customerRequestId);
                })
                ->whereIn('id', $idChunk)
                ->with(['customerRequest:id,public_id', 'merchant:id,public_id,status'])
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                continue;
            }

            $usersByMerchantId = $this->usersGroupedByMerchantId(
                $matches->pluck('merchant_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
            );

            foreach ($matches as $match) {
                $users = $usersByMerchantId->get((int) $match->merchant_id, collect());
                if ($users->isEmpty()) {
                    continue;
                }

                $callback($match, $users->values());
            }
        }
    }

    /**
     * Active memberships with RequestsView, keyed by merchant id.
     * Owners always qualify (same shortcut as MerchantPermissionService::membershipCan).
     *
     * @param  list<int>  $merchantIds
     * @return Collection<int, Collection<int, User>>
     */
    public function usersGroupedByMerchantId(array $merchantIds): Collection
    {
        $merchantIds = array_values(array_unique(array_filter($merchantIds, fn ($id) => is_int($id) && $id > 0)));
        if ($merchantIds === []) {
            return collect();
        }

        $memberships = MerchantUser::query()
            ->select('merchant_user.*')
            ->join('merchants', 'merchants.id', '=', 'merchant_user.merchant_id')
            ->join('users', 'users.id', '=', 'merchant_user.user_id')
            ->whereIn('merchant_user.merchant_id', $merchantIds)
            ->where('merchant_user.status', MembershipStatus::Active)
            ->where('merchants.status', MerchantStatus::Active)
            ->where('users.status', UserStatus::Active)
            ->where(function ($query) {
                $query->where('merchant_user.role', Role::Owner)
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('merchant_user_permissions')
                            ->join('merchant_permissions', 'merchant_permissions.id', '=', 'merchant_user_permissions.merchant_permission_id')
                            ->whereColumn('merchant_user_permissions.merchant_user_id', 'merchant_user.id')
                            ->where('merchant_permissions.key', PermissionKey::RequestsView->value);
                    });
            })
            ->with('user')
            ->orderBy('merchant_user.id')
            ->get();

        $grouped = collect();

        foreach ($memberships as $membership) {
            $user = $membership->user;
            if ($user === null) {
                continue;
            }

            $merchantId = (int) $membership->merchant_id;
            $bucket = $grouped->get($merchantId, collect());
            $bucket->put($user->id, $user);
            $grouped->put($merchantId, $bucket);
        }

        return $grouped;
    }
}
