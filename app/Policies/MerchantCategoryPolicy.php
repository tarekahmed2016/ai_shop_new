<?php

namespace App\Policies;

use App\Models\MerchantCategory;
use App\Models\User;
use App\Support\AdminAccess;

class MerchantCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'merchants.view');
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }

    public function update(User $user, MerchantCategory $merchantCategory): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }

    public function delete(User $user, MerchantCategory $merchantCategory): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }
}
