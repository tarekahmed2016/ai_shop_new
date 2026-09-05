<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Support\AdminAccess;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'categories.view');
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return AdminAccess::allows($user, 'categories.update');
    }
}
