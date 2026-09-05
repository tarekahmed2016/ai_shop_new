<?php

namespace App\Policies;

use App\Models\MerchantUser;
use App\Models\User;
use App\Support\AdminAccess;

class MerchantUserPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'merchants.view');
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }

    public function update(User $user, MerchantUser $merchantUser): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }

    public function delete(User $user, MerchantUser $merchantUser): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }
}
