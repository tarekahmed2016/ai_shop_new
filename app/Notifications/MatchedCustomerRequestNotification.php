<?php

namespace App\Notifications;

use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\RequestMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MatchedCustomerRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public const TYPE = 'matched_request';

    public const TITLE_AR = 'طلب جديد مناسب لنشاطك';

    public const TITLE_EN = 'New request matching your activity';

    public const BODY_AR = 'وصل طلب جديد ضمن أحد أنشطتك. اضغط لعرض التفاصيل.';

    public const BODY_EN = 'A new request matches one of your activities. Tap to view details.';

    public function __construct(public RequestMatch $requestMatch)
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
            ->title($payload['title'])
            ->body($payload['body'])
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
     *     merchant_public_id: string,
     *     destination_url: string,
     *     title: string,
     *     title_en: string,
     *     body: string,
     *     body_en: string,
     *     tag: string
     * }
     */
    public function safePayload(): array
    {
        $match = $this->requestMatch->loadMissing(['customerRequest:id,public_id', 'merchant:id,public_id']);
        $request = $match->customerRequest;
        $merchant = $match->merchant;

        if (! $request instanceof CustomerRequest || ! $merchant instanceof Merchant) {
            throw new InvalidArgumentException('Matched request notification is missing merchant or request.');
        }

        $requestPublicId = (string) $request->public_id;
        $merchantPublicId = (string) $merchant->public_id;

        return [
            'type' => self::TYPE,
            'request_public_id' => $requestPublicId,
            'merchant_public_id' => $merchantPublicId,
            'destination_url' => route('merchant.requests.open', [
                'merchant' => $merchantPublicId,
                'customerRequest' => $requestPublicId,
            ]),
            'title' => self::TITLE_AR,
            'title_en' => self::TITLE_EN,
            'body' => self::BODY_AR,
            'body_en' => self::BODY_EN,
            'tag' => 'matched-request-'.$merchantPublicId.'-'.$requestPublicId,
        ];
    }
}
