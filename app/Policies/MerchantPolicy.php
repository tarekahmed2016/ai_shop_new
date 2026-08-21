<?php

namespace App\Policies;

use App\Enums\MerchantMemberships\Status;
use App\Models\Merchant;
use App\Models\User;
use App\Services\MerchantContextService;

class MerchantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Merchant $merchant): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->merchantMemberships()
            ->where('merchant_id', $merchant->id)
            ->where('status', Status::Active)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return $user->hasRole('admin');
    }

    public function select(User $user, Merchant $merchant): bool
    {
        return app(MerchantContextService::class)
            ->activeMembership($user, $merchant) !== null
            && $merchant->isActive();
    }
}
