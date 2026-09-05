<?php

namespace App\Services;

use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Support\CustomerRequests\CustomerRequestMessages;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CustomerRequestLimitService
{
    public function __construct(
        public PlatformSettingService $platformSettingService,
        public CustomerExtraRequestService $customerExtraRequestService,
    ) {}

    public function timezone(): string
    {
        return (string) config('customer_requests.timezone', 'Asia/Muscat');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function todayUtcRange(?CarbonImmutable $now = null): array
    {
        $tz = $this->timezone();
        $local = ($now ?? CarbonImmutable::now($tz))->timezone($tz);

        return [
            $local->startOfDay()->timezone('UTC'),
            $local->endOfDay()->timezone('UTC'),
        ];
    }

    public function globalLimit(): int
    {
        return $this->platformSettingService->dailyCustomerRequestLimit();
    }

    public function effectiveLimit(Customer $customer): int
    {
        $override = $customer->daily_request_limit_override;

        if ($override === null) {
            return $this->globalLimit();
        }

        $max = max(1, (int) config('customer_requests.max_daily_limit', 100));

        return max(1, min($max, (int) $override));
    }

    /**
     * Count of requests that actually consumed a daily-quota slot today.
     *
     * The day-window bucketing intentionally stays keyed on `created_at`
     * (the day the customer submitted), but a row now only counts once
     * `quota_consumed_at` is set — i.e. once it has actually reached the
     * authoritative finalization gate (App\Jobs\FinalizeCustomerRequestJob).
     * Rows still mid-pipeline (queued/classifying/duplicate-checking/
     * reviewing), rows that failed classification, and rows blocked as
     * duplicates never set `quota_consumed_at`, so they are correctly
     * excluded here. Historical rows are backfilled with
     * `quota_consumed_at = created_at` (see the migration), which is
     * provably count-identical to the pre-existing raw-row-count query for
     * every row that existed before this pipeline, because under the old
     * synchronous code a Web/WhatsApp row was never persisted until quota
     * had already been consumed.
     */
    public function todayCount(Customer $customer, ?CarbonImmutable $now = null): int
    {
        [$start, $end] = $this->todayUtcRange($now);

        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->whereIn('source', [Source::Web, Source::WhatsApp])
            ->whereNotNull('quota_consumed_at')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Anti-abuse ceiling: how many non-Ready (still PendingClassification)
     * rows this customer currently has open, regardless of ai_stage. This
     * is independent of the daily accepted-request quota — quota is no
     * longer tied to row existence, so without this a customer could
     * otherwise accumulate unlimited unconfirmed AI-processing rows at real
     * (unmetered) AI cost.
     */
    public function openAttemptCount(Customer $customer): int
    {
        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->where('status', RequestStatus::PendingClassification)
            ->count();
    }

    public function maxOpenAiAttempts(): int
    {
        return CustomerRequestPipelineConfig::maxOpenAiAttempts();
    }

    public function assertOpenAttemptCeilingNotReached(Customer $customer): void
    {
        if ($this->openAttemptCount($customer) >= $this->maxOpenAiAttempts()) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::tooManyOpenAttempts(),
            ]);
        }
    }

    /**
     * Rule A1: at most one row per customer may occupy the classification
     * in-flight stage set at a time.
     */
    public function hasClassificationInFlight(Customer $customer, ?int $exceptRequestId = null): bool
    {
        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->when($exceptRequestId !== null, fn ($q) => $q->where('id', '!=', $exceptRequestId))
            ->whereIn('ai_stage', array_map(fn (AiStage $s) => $s->value, AiStage::classificationInFlight()))
            ->exists();
    }

    public function assertNoClassificationInFlight(Customer $customer, ?int $exceptRequestId = null): void
    {
        if ($this->hasClassificationInFlight($customer, $exceptRequestId)) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::classificationAlreadyInProgress(),
            ]);
        }
    }

    /**
     * Rule A2: at most one row per customer may occupy the finalization
     * in-flight stage set at a time.
     */
    public function hasFinalizationInFlight(Customer $customer, ?int $exceptRequestId = null): bool
    {
        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->when($exceptRequestId !== null, fn ($q) => $q->where('id', '!=', $exceptRequestId))
            ->whereIn('ai_stage', array_map(fn (AiStage $s) => $s->value, AiStage::finalizationInFlight()))
            ->exists();
    }

    public function assertNoFinalizationInFlight(Customer $customer, ?int $exceptRequestId = null): void
    {
        if ($this->hasFinalizationInFlight($customer, $exceptRequestId)) {
            throw ValidationException::withMessages([
                'category_id' => CustomerRequestMessages::classificationAlreadyInProgress(),
            ]);
        }
    }

    /**
     * @return array{
     *     timezone: string,
     *     global_limit: int,
     *     override: int|null,
     *     daily_limit: int,
     *     used: int,
     *     remaining: int,
     *     extra_request_balance: int,
     *     can_create: bool
     * }
     */
    public function snapshot(Customer $customer, ?CarbonImmutable $now = null): array
    {
        $global = $this->globalLimit();
        $effective = $this->effectiveLimit($customer);
        $used = $this->todayCount($customer, $now);
        $remaining = max(0, $effective - $used);
        $extra = $this->customerExtraRequestService->balance((int) $customer->id);

        return [
            'timezone' => $this->timezone(),
            'global_limit' => $global,
            'override' => $customer->daily_request_limit_override,
            'daily_limit' => $effective,
            'used' => $used,
            'remaining' => $remaining,
            'extra_request_balance' => $extra,
            'can_create' => $remaining > 0 || $extra > 0,
        ];
    }

    public function dailyQuotaExhausted(Customer $customer, ?CarbonImmutable $now = null): bool
    {
        return $this->todayCount($customer, $now) >= $this->effectiveLimit($customer);
    }

    public function assertWithinLimit(Customer $customer): void
    {
        if (! $this->dailyQuotaExhausted($customer)) {
            return;
        }

        if ($this->customerExtraRequestService->balance((int) $customer->id) > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'request_text' => CustomerRequestMessages::dailyLimitReached(),
        ]);
    }
}
