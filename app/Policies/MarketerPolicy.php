<?php

namespace App\Policies;

use App\Models\Marketer;
use App\Models\User;

class MarketerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Marketer $marketer): bool
    {
        return $user->hasRole('admin') || $user->id === $marketer->user_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Marketer $marketer): bool
    {
        return $user->hasRole('admin');
    }

    public function approve(User $user, Marketer $marketer): bool
    {
        return $user->hasRole('admin');
    }

    public function reject(User $user, Marketer $marketer): bool
    {
        return $user->hasRole('admin');
    }

    public function deactivate(User $user, Marketer $marketer): bool
    {
        return $user->hasRole('admin');
    }

    public function reactivate(User $user, Marketer $marketer): bool
    {
        return $user->hasRole('admin');
    }
}
