<?php

namespace App\Services;

use App\Enums\ActivityLogs\Event;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\RequestMatch;
use App\Support\MerchantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestMatchingService
{
    /**
     * @var list<string>
     */
    private const MATCH_ACTIVITY_FIELDS = [
        'customer_request_id',
        'merchant_id',
        'status',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public MerchantContext $merchantContext,
        public MerchantRequestMatchService $merchantRequestMatchService,
    ) {}

    /**
     * Synchronize request_matches for exact category matching.
     *
     * Strategy:
     * - Eligible merchants: active, with the request's exact category assigned.
     * - Create missing Pending matches (idempotent).
     * - Delete matches for merchants that no longer qualify (any status).
     * - Preserve Pending/Viewed/Dismissed for merchants that still qualify.
     *
     * Ineligible requests (no category, inactive category, Closed/Cancelled)
     * result in an empty eligible set, so leftover matches are removed.
     *
     * @return array{eligible: bool, reason: string|null, created: int, created_match_ids: list<int>, removed: int, retained: int}
     */
    public function sync(CustomerRequest $customerRequest, bool $strict = false): array
    {
        $result = DB::transaction(function () use ($customerRequest) {
            $locked = CustomerRequest::query()
                ->whereKey($customerRequest->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return [
                    'eligible' => false,
                    'reason' => null,
                    'created' => 0,
                    'created_match_ids' => [],
                    'removed' => 0,
                    'retained' => 0,
                ];
            }

            $locked->loadMissing('category');

            $reason = $this->ineligibilityReason($locked);
            $eligibleMerchantIds = $reason === null
                ? $this->eligibleMerchantIds($locked)
                : collect();

            $this->merchantRequestMatchService->recordEligibleMerchants(
                $locked,
                $eligibleMerchantIds,
            );

            $existingByMerchantId = RequestMatch::query()
                ->where('customer_request_id', $locked->id)
                ->pluck('id', 'merchant_id');

            $eligibleIdList = $eligibleMerchantIds->map(fn ($id) => (int) $id)->all();
            $eligibleLookup = array_fill_keys($eligibleIdList, true);
            $chunkSize = $this->writeChunkSize();
            $removed = 0;

            if ($eligibleIdList === []) {
                $removed = $existingByMerchantId->count();
                if ($removed > 0) {
                    RequestMatch::query()
                        ->where('customer_request_id', $locked->id)
                        ->delete();
                }
                $retained = 0;
                $createdMatchIds = [];
            } else {
                $obsoleteIds = [];
                foreach ($existingByMerchantId as $merchantId => $matchId) {
                    if (! isset($eligibleLookup[(int) $merchantId])) {
                        $obsoleteIds[] = (int) $matchId;
                    }
                }

                if ($obsoleteIds !== []) {
                    $removed = count($obsoleteIds);
                    foreach (array_chunk($obsoleteIds, $chunkSize) as $obsoleteChunk) {
                        RequestMatch::query()->whereIn('id', $obsoleteChunk)->delete();
                    }
                }

                $existingMerchantLookup = array_flip($existingByMerchantId->keys()->map(fn ($id) => (int) $id)->all());
                $newMerchantIds = [];
                foreach ($eligibleIdList as $merchantId) {
                    $merchantId = (int) $merchantId;
                    if (! isset($existingMerchantLookup[$merchantId])) {
                        $newMerchantIds[] = $merchantId;
                    }
                }

                $createdMatchIds = $this->insertPendingMatches((int) $locked->id, $newMerchantIds, $chunkSize);
                $retained = count($eligibleIdList) - count($newMerchantIds);
            }

            return [
                'eligible' => $reason === null,
                'reason' => $reason,
                'created' => count($createdMatchIds),
                'created_match_ids' => $createdMatchIds,
                'removed' => $removed,
                'retained' => $retained,
            ];
        });

        $this->logSyncSummary($customerRequest, $result);

        app(MatchedRequestPushDispatcher::class)->dispatchAfterCommit(
            (int) $customerRequest->id,
            $result['created_match_ids'] ?? [],
        );

        app(MerchantOfferService::class)->invalidateSubmittedOffersMissingMatch($customerRequest);

        $this->markMatchingCompletedIfReady($customerRequest);

        if ($strict && $result['reason'] !== null) {
            throw ValidationException::withMessages([
                'category_id' => $result['reason'],
            ]);
        }

        return $result;
    }

    /**
     * A successful sync — including zero eligible merchants — means
     * matching is done. Only Ready rows are marked; cancelled/closed
     * requests must stay eligible for no-op recovery.
     */
    private function markMatchingCompletedIfReady(CustomerRequest $customerRequest): void
    {
        CustomerRequest::query()
            ->whereKey($customerRequest->id)
            ->where('status', RequestStatus::Ready)
            ->whereNull('matching_completed_at')
            ->update(['matching_completed_at' => now()]);
    }

    public function removeMatchesForMerchantCategory(Merchant $merchant, int $categoryId): int
    {
        $matches = RequestMatch::query()
            ->where('merchant_id', $merchant->id)
            ->whereHas('customerRequest', fn ($query) => $query->where('category_id', $categoryId))
            ->get();

        $count = $matches->count();

        if ($count === 0) {
            return 0;
        }

        RequestMatch::query()->whereIn('id', $matches->pluck('id'))->delete();

        foreach ($matches->pluck('customer_request_id')->unique() as $customerRequestId) {
            app(MerchantOfferService::class)->invalidateSubmittedOfferIfUnmatched(
                (int) $customerRequestId,
                $merchant->id,
            );
        }

        return $count;
    }

    public function markViewed(CustomerRequest $customerRequest): ?RequestMatch
    {
        $match = $this->currentMerchantMatch($customerRequest);

        if ($match === null || ! $match->isVisibleToMerchant()) {
            return null;
        }

        if ($match->status === MatchStatus::Viewed) {
            return $match;
        }

        $originalValues = $match->only(self::MATCH_ACTIVITY_FIELDS);
        $match->status = MatchStatus::Viewed;
        $match->save();

        $this->activityLogService->recordChanges(
            subject: $match,
            originalValues: $originalValues,
            allowedFields: self::MATCH_ACTIVITY_FIELDS,
            subjectLabel: 'viewed',
        );

        return $match->fresh();
    }

    public function dismissForCurrentMerchant(CustomerRequest $customerRequest): RequestMatch
    {
        $match = $this->currentMerchantMatch($customerRequest);

        if ($match === null || ! $match->isVisibleToMerchant()) {
            abort(403);
        }

        if ($match->status === MatchStatus::Dismissed) {
            return $match;
        }

        $originalValues = $match->only(self::MATCH_ACTIVITY_FIELDS);
        $match->status = MatchStatus::Dismissed;
        $match->save();

        $this->activityLogService->recordChanges(
            subject: $match,
            originalValues: $originalValues,
            allowedFields: self::MATCH_ACTIVITY_FIELDS,
            subjectLabel: 'dismissed',
        );

        return $match->fresh();
    }

    public function currentMerchantMatch(CustomerRequest $customerRequest): ?RequestMatch
    {
        if (! $this->merchantContext->isActive()) {
            return null;
        }

        return RequestMatch::query()
            ->where('customer_request_id', $customerRequest->id)
            ->where('merchant_id', $this->merchantContext->merchantId())
            ->first();
    }

    /**
     * @return Collection<int, int>
     */
    public function eligibleMerchantIds(CustomerRequest $customerRequest): Collection
    {
        if ($customerRequest->category_id === null) {
            return collect();
        }

        return Merchant::query()
            ->where('status', MerchantStatus::Active)
            ->whereHas('categoryAssignments', function ($query) use ($customerRequest) {
                $query->where('category_id', $customerRequest->category_id);
            })
            ->orderBy('id')
            ->pluck('id');
    }

    public function ineligibilityReason(CustomerRequest $customerRequest): ?string
    {
        if ($customerRequest->category_id === null) {
            return 'This request has no category and cannot be matched.';
        }

        if ($customerRequest->status === RequestStatus::Closed || $customerRequest->status === RequestStatus::Cancelled) {
            return 'Closed or cancelled requests cannot be matched.';
        }

        $category = $customerRequest->relationLoaded('category')
            ? $customerRequest->category
            : $customerRequest->category()->first();

        if ($category === null || ! $category->isActive()) {
            return 'The request category is inactive and cannot be matched.';
        }

        return null;
    }

    private function writeChunkSize(): int
    {
        return max(1, min(500, (int) config('notifications.matched_request_chunk_size', 200)));
    }

    /**
     * @param  list<int>  $merchantIds
     * @return list<int>
     */
    private function insertPendingMatches(int $customerRequestId, array $merchantIds, int $chunkSize): array
    {
        if ($merchantIds === []) {
            return [];
        }

        $now = now();
        foreach (array_chunk($merchantIds, $chunkSize) as $chunk) {
            $rows = [];
            foreach ($chunk as $merchantId) {
                $rows[] = [
                    'customer_request_id' => $customerRequestId,
                    'merchant_id' => $merchantId,
                    'status' => MatchStatus::Pending->value,
                    'matched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            RequestMatch::query()->insert($rows);
        }

        $createdMatchIds = [];
        foreach (array_chunk($merchantIds, $chunkSize) as $chunk) {
            $createdMatchIds = array_merge(
                $createdMatchIds,
                RequestMatch::query()
                    ->where('customer_request_id', $customerRequestId)
                    ->whereIn('merchant_id', $chunk)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            );
        }

        return $createdMatchIds;
    }

    /**
     * @param  array{eligible: bool, reason: string|null, created: int, removed: int, retained: int}  $result
     */
    private function logSyncSummary(CustomerRequest $customerRequest, array $result): void
    {
        if ($result['created'] === 0 && $result['removed'] === 0) {
            return;
        }

        $this->activityLogService->recordSystem(
            subject: $customerRequest,
            event: Event::Updated,
            oldValues: [],
            newValues: [
                'created' => $result['created'],
                'removed' => $result['removed'],
                'retained' => $result['retained'],
            ],
            allowedFields: ['created', 'removed', 'retained'],
            subjectLabel: 'matching',
            metadata: [
                'action' => 'matching.sync',
                'created' => $result['created'],
                'removed' => $result['removed'],
                'retained' => $result['retained'],
                'eligible' => $result['eligible'],
            ],
        );
    }
}
