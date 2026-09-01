<?php

namespace App\Services;

use App\Contracts\AiDuplicateDetectionProviderInterface;
use App\Enums\CustomerRequests\Source;
use App\Exceptions\DuplicateCustomerRequestException;
use App\Exceptions\DuplicateDetectionFailedException;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Support\CustomerRequests\NormalizedRequestSnapshot;
use App\Support\DuplicateDetection\DuplicateDetectionInput;
use App\Support\DuplicateDetection\DuplicateDetectionResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerRequestDuplicateDetectionService
{
    public function __construct(
        public AiDuplicateDetectionProviderInterface $provider,
        public NormalizedRequestSnapshotService $snapshotService,
    ) {}

    /**
     * Serialize create attempts per customer, then persist only if the new snapshot is not a duplicate.
     *
     * @param  array<string, mixed>  $newSnapshot
     * @param  callable(): CustomerRequest  $persist
     */
    public function persistIfNotDuplicate(Customer $customer, array $newSnapshot, callable $persist, ?int $excludeRequestId = null): CustomerRequest
    {
        return $this->runSerialized($customer, function () use ($customer, $newSnapshot, $persist, $excludeRequestId) {
            $this->assertNotDuplicate($customer, $newSnapshot, $excludeRequestId);
            $request = $persist();
            $this->snapshotService->store($request, $newSnapshot);

            return $request->fresh(['image']);
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runSerialized(Customer $customer, callable $callback): mixed
    {
        $lock = Cache::lock($this->lockKey((int) $customer->id), 30);

        return $lock->block(20, $callback);
    }

    /**
     * @param  array<string, mixed>  $newSnapshot
     */
    public function assertNotDuplicate(Customer $customer, array $newSnapshot, ?int $excludeRequestId = null): void
    {
        if (! NormalizedRequestSnapshot::isComparable($newSnapshot)) {
            return;
        }

        $previous = $this->previousSnapshots($customer, $excludeRequestId);
        if ($previous->isEmpty()) {
            return;
        }

        $allowedIds = $previous->map(fn (array $row) => (int) ($row['id'] ?? 0))->filter()->values()->all();
        $input = new DuplicateDetectionInput(
            newRequest: NormalizedRequestSnapshot::sanitize($newSnapshot),
            previousRequests: $previous->all(),
        );

        try {
            $decision = $this->provider->detect($input);
        } catch (DuplicateDetectionFailedException $exception) {
            Log::warning('customer_request.duplicate_check_failed', [
                'customer_id' => $customer->id,
                'reason' => $exception->getMessage(),
            ]);

            return;
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('customer_request.duplicate_check_failed', [
                'customer_id' => $customer->id,
                'reason' => 'provider-unavailable',
            ]);

            return;
        }

        $threshold = $this->confidenceThreshold();
        if (! $decision->shouldBlock($threshold)) {
            return;
        }

        if ($decision->matchedRequestId === null || ! in_array($decision->matchedRequestId, $allowedIds, true)) {
            Log::warning('customer_request.duplicate_check_invalid_match', [
                'customer_id' => $customer->id,
                'matched_request_id' => $decision->matchedRequestId,
            ]);

            return;
        }

        $matched = CustomerRequest::query()->find($decision->matchedRequestId);
        if (! $matched instanceof CustomerRequest || (int) $matched->customer_id !== (int) $customer->id) {
            return;
        }

        $this->logBlocked($customer, $matched, $decision);

        throw new DuplicateCustomerRequestException(
            matchedRequest: $matched,
            confidence: $decision->confidence,
            reasonCode: $decision->reasonCode,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function previousSnapshots(Customer $customer, ?int $excludeRequestId = null): Collection
    {
        $limit = $this->previousLimit();

        $rows = CustomerRequest::query()
            ->with([
                'category:id,public_id,name_ar,name_en',
                'latestClassification.suggestedCategory:id,public_id,name_ar,name_en',
                'latestClassification.confirmedCategory:id,public_id,name_ar,name_en',
            ])
            ->where('customer_id', $customer->id)
            ->whereIn('source', [Source::Web, Source::WhatsApp])
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->orderByDesc('id')
            ->limit(max($limit * 3, $limit))
            ->get();

        return $rows
            ->map(fn (CustomerRequest $request) => $this->snapshotService->fromPersisted($request))
            ->filter(fn (array $snapshot) => NormalizedRequestSnapshot::isComparable($snapshot))
            ->take($limit)
            ->values();
    }

    public function confidenceThreshold(): float
    {
        $value = (float) config('customer_requests.duplicate_confidence', 0.90);

        return max(0, min(1, $value));
    }

    public function previousLimit(): int
    {
        return max(1, (int) config('customer_requests.duplicate_previous_limit', 6));
    }

    public function lockKey(int $customerId): string
    {
        return 'customer-request-create:'.$customerId;
    }

    private function logBlocked(Customer $customer, CustomerRequest $matched, DuplicateDetectionResult $decision): void
    {
        Log::info('customer_request.duplicate_blocked', [
            'customer_id' => $customer->id,
            'matched_request_id' => $matched->id,
            'confidence' => $decision->confidence,
            'reason_code' => $decision->reasonCode,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
