<?php

namespace App\Services;

use App\Enums\MerchantPermissions\PermissionKey;
use App\Enums\Users\Status as UserStatus;
use App\Models\RequestMatch;
use App\Models\User;
use Illuminate\Support\Collection;

class MatchedRequestRecipientResolver
{
    public function __construct(
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function usersFor(RequestMatch $match): Collection
    {
        $match->loadMissing(['merchant.memberships.user', 'merchant.memberships.permissions']);

        $merchant = $match->merchant;
        if ($merchant === null || ! $merchant->isActive()) {
            return collect();
        }

        $users = collect();

        foreach ($merchant->memberships as $membership) {
            if (! $membership->isActive()) {
                continue;
            }

            $user = $membership->user;
            if ($user === null || $user->status !== UserStatus::Active) {
                continue;
            }

            if (! $this->merchantPermissionService->membershipCan($membership, PermissionKey::RequestsView->value)) {
                continue;
            }

            $users->put($user->id, $user);
        }

        return $users->values();
    }
}
