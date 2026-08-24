<?php

namespace App\Support;

use App\Models\MerchantUser;
use App\Models\User;

class UserCapabilities
{
    /**
     * Independent marketplace capabilities for an authenticated User.
     * These flags are not mutually exclusive.
     *
     * @return array{
     *     hasCustomer: bool,
     *     hasActiveCustomer: bool,
     *     hasMerchantMemberships: bool,
     *     hasActiveMerchantMemberships: bool,
     *     merchantCount: int,
     *     activeMerchantCount: int,
     *     hasMarketer: bool,
     *     hasActiveMarketer: bool,
     *     marketerStatus: string|null
     * }
     */
    public static function for(User $user): array
    {
        $user->loadMissing(['customer', 'merchantMemberships.merchant', 'marketer']);

        $customer = $user->customer;
        $memberships = $user->merchantMemberships;
        $marketer = $user->marketer;

        $activeMemberships = $memberships->filter(function (MerchantUser $membership) {
            return $membership->isActive()
                && $membership->merchant?->isActive() === true;
        });

        return [
            'hasCustomer' => $customer !== null,
            'hasActiveCustomer' => $customer?->isActive() === true,
            'hasMerchantMemberships' => $memberships->isNotEmpty(),
            'hasActiveMerchantMemberships' => $activeMemberships->isNotEmpty(),
            'merchantCount' => $memberships->count(),
            'activeMerchantCount' => $activeMemberships->count(),
            'hasMarketer' => $marketer !== null,
            'hasActiveMarketer' => $marketer?->isActive() === true,
            'marketerStatus' => $marketer?->status?->name,
        ];
    }
}
