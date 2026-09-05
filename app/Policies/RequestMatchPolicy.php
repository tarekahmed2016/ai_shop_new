<?php

namespace App\Policies;

use App\Models\RequestMatch;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\MerchantContext;

class RequestMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'matching.view');
    }

    public function view(User $user, RequestMatch $requestMatch): bool
    {
        if (AdminAccess::allows($user, 'matching.view')) {
            return true;
        }

        return $this->ownsActiveMatch($requestMatch);
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'matching.recalculate');
    }

    public function update(User $user, RequestMatch $requestMatch): bool
    {
        if (AdminAccess::allows($user, 'matching.recalculate')) {
            return true;
        }

        return $this->ownsActiveMatch($requestMatch) && $requestMatch->isVisibleToMerchant();
    }

    public function delete(User $user, RequestMatch $requestMatch): bool
    {
        return AdminAccess::allows($user, 'matching.recalculate');
    }

    public function match(User $user): bool
    {
        return AdminAccess::allows($user, 'matching.recalculate');
    }

    private function ownsActiveMatch(RequestMatch $requestMatch): bool
    {
        $context = app(MerchantContext::class);

        if (! $context->isActive()) {
            return false;
        }

        return $requestMatch->merchant_id === $context->merchantId();
    }
}
