<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\MerchantOffer;
use App\Models\MerchantRequestMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MerchantRequestMatchService
{
    /**
     * Persist marketplace eligibility for a request. Never deletes historical rows.
     *
     * @param  iterable<int|string>  $merchantIds
     */
    public function recordEligibleMerchants(CustomerRequest $customerRequest, iterable $merchantIds): int
    {
        $ids = Collection::make($merchantIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $now = Carbon::now();
        $chunkSize = max(1, min(500, (int) config('notifications.matched_request_chunk_size', 200)));
        $inserted = 0;

        foreach ($ids->chunk($chunkSize) as $chunk) {
            $rows = $chunk->map(fn (int $merchantId) => [
                'merchant_id' => $merchantId,
                'customer_request_id' => $customerRequest->id,
                'matched_category_id' => $customerRequest->category_id,
                'matched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            $inserted += (int) MerchantRequestMatch::query()->insertOrIgnore($rows);
        }

        return $inserted;
    }

    public function requestsReceivedCount(int $merchantId): int
    {
        return MerchantRequestMatch::query()
            ->where('merchant_id', $merchantId)
            ->count();
    }

    public function offersSubmittedCount(int $merchantId): int
    {
        return MerchantOffer::query()
            ->where('merchant_id', $merchantId)
            ->forTrackedSubmittedResponse()
            ->count();
    }

    /**
     * @return array{requests_received: int, offers_submitted: int}
     */
    public function usageCounters(int $merchantId): array
    {
        return [
            'requests_received' => $this->requestsReceivedCount($merchantId),
            'offers_submitted' => $this->offersSubmittedCount($merchantId),
        ];
    }
}
