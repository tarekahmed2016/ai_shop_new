<?php

namespace App\Notifications;

use App\Models\CustomerRequest;
use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class CustomerOfferReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'customer_offer_received';

    public const TITLE_AR = 'وصلك عرض جديد';

    public const TITLE_EN = 'New offer received';

    public const BODY_AR = 'وصل عرض جديد على طلبك. اضغط لعرض التفاصيل.';

    public const BODY_EN = 'A new offer was submitted for your request. Tap to view details.';

    public function __construct(public MerchantOffer $offer)
    {
        $this->afterCommit();
    }

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $payload = $this->safePayload();

        return (new WebPushMessage)
            ->title($payload['title_ar'])
            ->body($payload['body_ar'])
            ->icon('/icons/pwa-192.png')
            ->badge('/icons/pwa-192.png')
            ->tag($payload['tag'])
            ->renotify()
            ->data($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->safePayload();
    }

    /**
     * @return array{
     *     type: string,
     *     request_public_id: string,
     *     offer_public_id: string,
     *     destination_url: string,
     *     title: string,
     *     title_ar: string,
     *     title_en: string,
     *     body: string,
     *     body_ar: string,
     *     body_en: string,
     *     tag: string
     * }
     */
    public function safePayload(): array
    {
        $offer = $this->offer->loadMissing(['customerRequest:id,public_id']);
        $request = $offer->customerRequest;

        if (! $request instanceof CustomerRequest || $offer->public_id === null) {
            throw new InvalidArgumentException('Customer offer notification is missing request or offer public id.');
        }

        $requestPublicId = (string) $request->public_id;
        $offerPublicId = (string) $offer->public_id;

        return [
            'type' => self::TYPE,
            'request_public_id' => $requestPublicId,
            'offer_public_id' => $offerPublicId,
            'destination_url' => route('customer.requests.show', $requestPublicId, false),
            'title' => self::TITLE_AR,
            'title_ar' => self::TITLE_AR,
            'title_en' => self::TITLE_EN,
            'body' => self::BODY_AR,
            'body_ar' => self::BODY_AR,
            'body_en' => self::BODY_EN,
            'tag' => 'customer-offer-'.$offerPublicId,
        ];
    }
}
