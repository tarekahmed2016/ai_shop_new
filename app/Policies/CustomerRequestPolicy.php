<?php

namespace App\Policies;

use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\CustomerRequest;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use App\Support\MerchantContext;

class CustomerRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, CustomerRequest $customerRequest): bool
    {
        return $user->hasRole('admin');
    }

    public function viewMatchedAny(User $user): bool
    {
        return app(MerchantPermissionService::class)->currentCan(PermissionKey::RequestsView->value);
    }

    public function viewMatched(User $user, CustomerRequest $customerRequest): bool
    {
        if (! app(MerchantPermissionService::class)->currentCan(PermissionKey::RequestsViewDetails->value)) {
            return false;
        }

        if (! app(MerchantContext::class)->isActive()) {
            return false;
        }

        $match = app(RequestMatchingService::class)->currentMerchantMatch($customerRequest);

        return $match !== null && $match->isVisibleToMerchant();
    }

    public function dismissMatched(User $user, CustomerRequest $customerRequest): bool
    {
        if (! app(MerchantPermissionService::class)->currentCan(PermissionKey::RequestsDismiss->value)) {
            return false;
        }

        if (! app(MerchantContext::class)->isActive()) {
            return false;
        }

        $match = app(RequestMatchingService::class)->currentMerchantMatch($customerRequest);

        return $match !== null && $match->isVisibleToMerchant();
    }

    public function viewOwn(User $user, CustomerRequest $customerRequest): bool
    {
        $customer = $user->customer;

        return $customer !== null
            && $customer->isActive()
            && (int) $customer->id === (int) $customerRequest->customer_id;
    }

    public function createOwn(User $user): bool
    {
        return $user->customer?->isActive() === true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, CustomerRequest $customerRequest): bool
    {
        return $user->hasRole('admin');
    }

    public function match(User $user, CustomerRequest $customerRequest): bool
    {
        return $user->hasRole('admin');
    }
}
