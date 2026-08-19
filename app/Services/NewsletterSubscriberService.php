<?php

namespace App\Services;

use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NewsletterSubscriberService
{
    /**
     * @param  array{email: string}  $data
     */
    public function subscribe(array $data): NewsletterSubscriber
    {
        return DB::transaction(function () use ($data) {
            $existing = NewsletterSubscriber::query()
                ->where('email', $data['email'])
                ->first();

            if ($existing) {
                if (! $existing->is_active) {
                    $existing->update(['is_active' => true]);

                    return $existing->fresh();
                }

                return $existing;
            }

            return NewsletterSubscriber::create([
                'email' => $data['email'],
                'is_active' => true,
            ]);
        });
    }

    public function getPaginatedSubscribers(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return NewsletterSubscriber::query()
            ->when($search, fn ($q) => $q->where('email', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function delete(NewsletterSubscriber $subscriber): void
    {
        DB::transaction(function () use ($subscriber) {
            $subscriber->delete();
        });
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): NewsletterSubscriber
    {
        $subscriber->update(['is_active' => false]);

        return $subscriber->fresh();
    }
}
