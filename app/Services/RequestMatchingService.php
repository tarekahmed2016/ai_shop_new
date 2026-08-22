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
     * @return array{eligible: bool, reason: string|null, created: int, removed: int, retained: int}
     */
    public function sync(CustomerRequest $customerRequest, bool $strict = false): array
    {
        $customerRequest->loadMissing('category');

        $reason = $this->ineligibilityReason($customerRequest);
        $eligibleMerchantIds = $reason === null
            ? $this->eligibleMerchantIds($customerRequest)
            : collect();

        $result = DB::transaction(function () use ($customerRequest, $eligibleMerchantIds, $reason) {
            $existing = RequestMatch::query()
                ->where('customer_request_id', $customerRequest->id)
                ->get();

            $eligibleIdList = $eligibleMerchantIds->all();
            $removed = 0;

            if ($eligibleIdList === []) {
                $removed = $existing->count();
                if ($removed > 0) {
                    RequestMatch::query()
                        ->where('customer_request_id', $customerRequest->id)
                        ->delete();
                }
                $existingAfterRemoval = collect();
            } else {
                $obsoleteIds = $existing
                    ->reject(fn (RequestMatch $match) => in_array($match->merchant_id, $eligibleIdList, true))
                    ->pluck('id');

                if ($obsoleteIds->isNotEmpty()) {
                    $removed = $obsoleteIds->count();
                    RequestMatch::query()->whereIn('id', $obsoleteIds)->delete();
                }

                $existingAfterRemoval = $existing
                    ->filter(fn (RequestMatch $match) => in_array($match->merchant_id, $eligibleIdList, true))
                    ->values();
            }

            $existingMerchantIds = $existingAfterRemoval->pluck('merchant_id')->all();
            $created = 0;

            foreach ($eligibleIdList as $merchantId) {
                if (in_array($merchantId, $existingMerchantIds, true)) {
                    continue;
                }

                $match = new RequestMatch;
                $match->customer_request_id = $customerRequest->id;
                $match->merchant_id = $merchantId;
                $match->status = MatchStatus::Pending;
                $match->matched_at = now();
                $match->save();
                $created++;
            }

            return [
                'eligible' => $reason === null,
                'reason' => $reason,
                'created' => $created,
                'removed' => $removed,
                'retained' => $existingAfterRemoval->count(),
            ];
        });

        $this->logSyncSummary($customerRequest, $result);

        app(MerchantOfferService::class)->invalidateSubmittedOffersMissingMatch($customerRequest);

        if ($strict && $reason !== null) {
            throw ValidationException::withMessages([
                'category_id' => $reason,
            ]);
        }

        return $result;
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
