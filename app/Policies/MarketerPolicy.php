<?php

namespace App\Policies;

use App\Models\Marketer;
use App\Models\User;
use App\Support\AdminAccess;

class MarketerPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'marketers.view');
    }

    public function view(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.view')
            || $user->id === $marketer->user_id;
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'marketers.create');
    }

    public function update(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.update');
    }

    public function approve(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.approve');
    }

    public function reject(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.reject');
    }

    public function deactivate(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.activate');
    }

    public function reactivate(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.activate');
    }

    public function recordPayout(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.payout');
    }

    public function updateCommissionRates(User $user, Marketer $marketer): bool
    {
        return AdminAccess::allows($user, 'marketers.update');
    }

    public function manageCommissionSettings(User $user): bool
    {
        return AdminAccess::allows($user, 'marketer-commissions.manage-settings');
    }
}
