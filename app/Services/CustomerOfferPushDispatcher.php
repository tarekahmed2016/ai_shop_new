<?php

namespace App\Services;

use App\Models\MerchantOffer;
use App\Notifications\CustomerOfferReceivedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CustomerOfferPushDispatcher
{
    public function __construct(
        public CustomerOfferRecipientResolver $recipientResolver,
    ) {}

    public function dispatchAfterCommit(int $offerId): void
    {
        if ($offerId < 1) {
            return;
        }

        DB::afterCommit(function () use ($offerId): void {
            try {
                $this->notify($offerId);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function notify(int $offerId): void
    {
        $offer = MerchantOffer::query()
            ->with(['customerRequest:id,public_id,customer_id', 'customerRequest.customer.user'])
            ->find($offerId);

        if ($offer === null) {
            return;
        }

        $user = $this->recipientResolver->userFor($offer);
        if ($user === null) {
            return;
        }

        Notification::send($user, new CustomerOfferReceivedNotification($offer));
    }
}
