<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\AdminAccess;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return AdminAccess::allows($user, 'customers.view');
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return AdminAccess::allows($user, 'customers.update');
    }

    public function manageLimits(User $user, ?Customer $customer = null): bool
    {
        return AdminAccess::allows($user, 'customers.manage-limits');
    }
}
