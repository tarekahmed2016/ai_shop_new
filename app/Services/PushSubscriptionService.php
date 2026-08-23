<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionService
{
    /**
     * @return array{vapid_public_key: string, supported: bool, subscription_count: int}
     */
    public function config(User $user): array
    {
        $publicKey = (string) config('webpush.vapid.public_key', '');

        return [
            'vapid_public_key' => $publicKey,
            'supported' => $publicKey !== '',
            'subscription_count' => $user->pushSubscriptions()->count(),
        ];
    }

    /**
     * Bind the unique browser endpoint to the authenticated User.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(User $user, array $data): PushSubscription
    {
        $endpoint = (string) $data['endpoint'];
        $publicKey = (string) data_get($data, 'keys.p256dh');
        $authToken = (string) data_get($data, 'keys.auth');
        $contentEncoding = (string) ($data['contentEncoding'] ?? 'aes128gcm');

        try {
            return DB::transaction(function () use ($user, $endpoint, $publicKey, $authToken, $contentEncoding): PushSubscription {
                $existing = PushSubscription::query()
                    ->where('endpoint', $endpoint)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $this->assignToUser($existing, $user, $publicKey, $authToken, $contentEncoding);
                }

                return $user->pushSubscriptions()->create([
                    'endpoint' => $endpoint,
                    'public_key' => $publicKey,
                    'auth_token' => $authToken,
                    'content_encoding' => $contentEncoding,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PushSubscription::query()
                ->where('endpoint', $endpoint)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->assignToUser($existing, $user, $publicKey, $authToken, $contentEncoding);
        }
    }

    public function destroy(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()
            ->where('endpoint', $endpoint)
            ->delete();
    }

    private function assignToUser(
        PushSubscription $subscription,
        User $user,
        string $publicKey,
        string $authToken,
        string $contentEncoding,
    ): PushSubscription {
        $subscription->subscribable_type = $user->getMorphClass();
        $subscription->subscribable_id = $user->getKey();
        $subscription->public_key = $publicKey;
        $subscription->auth_token = $authToken;
        $subscription->content_encoding = $contentEncoding;
        $subscription->save();

        return $subscription;
    }
}
