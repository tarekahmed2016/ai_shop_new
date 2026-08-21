<?php

namespace App\Policies;

use App\Models\RequestMatch;
use App\Models\User;
use App\Support\MerchantContext;

class RequestMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, RequestMatch $requestMatch): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->ownsActiveMatch($requestMatch);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, RequestMatch $requestMatch): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->ownsActiveMatch($requestMatch) && $requestMatch->isVisibleToMerchant();
    }

    public function delete(User $user, RequestMatch $requestMatch): bool
    {
        return $user->hasRole('admin');
    }

    public function match(User $user): bool
    {
        return $user->hasRole('admin');
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
