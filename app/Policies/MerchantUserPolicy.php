<?php

namespace App\Policies;

use App\Models\MerchantUser;
use App\Models\User;

class MerchantUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, MerchantUser $merchantUser): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, MerchantUser $merchantUser): bool
    {
        return $user->hasRole('admin');
    }
}
