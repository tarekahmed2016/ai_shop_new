<?php

namespace App\Services;

use App\Enums\Users\Status as UserStatus;
use App\Models\MerchantOffer;
use App\Models\User;

class CustomerOfferRecipientResolver
{
    public function userFor(MerchantOffer $offer): ?User
    {
        $offer->loadMissing(['customerRequest.customer.user']);

        $customer = $offer->customerRequest?->customer;
        if ($customer === null || ! $customer->isActive()) {
            return null;
        }

        $user = $customer->user;
        if ($user === null || $user->status !== UserStatus::Active) {
            return null;
        }

        return $user;
    }
}
