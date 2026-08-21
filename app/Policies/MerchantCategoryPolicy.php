<?php

namespace App\Policies;

use App\Models\MerchantCategory;
use App\Models\User;

class MerchantCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, MerchantCategory $merchantCategory): bool
    {
        return $user->hasRole('admin');
    }
}
