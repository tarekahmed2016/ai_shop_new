<?php

namespace App\Policies;

use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Support\MerchantContext;

class MerchantOfferPolicy
{
    public function view(User $user, MerchantOffer $offer): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $this->customerMayView($user, $offer)
            || $this->merchantMayView($user, $offer);
    }

    public function viewImage(User $user, MerchantOffer $offer, MerchantOfferImage $image): bool
    {
        if ((int) $image->merchant_offer_id !== (int) $offer->id) {
            return false;
        }

        return $this->view($user, $offer);
    }

    private function customerMayView(User $user, MerchantOffer $offer): bool
    {
        $customer = $user->customer;
        if ($customer === null || ! $customer->isActive()) {
            return false;
        }

        $offer->loadMissing('customerRequest');

        return $offer->status === OfferStatus::Submitted
            && (int) $offer->customerRequest?->customer_id === (int) $customer->id;
    }

    private function merchantMayView(User $user, MerchantOffer $offer): bool
    {
        $context = app(MerchantContext::class);

        if (! $context->isActive()) {
            return false;
        }

        if (! app(MerchantPermissionService::class)->currentCan(PermissionKey::OffersView->value)) {
            return false;
        }

        return (int) $context->merchantId() === (int) $offer->merchant_id;
    }
}
