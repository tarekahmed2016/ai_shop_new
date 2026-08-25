<?php

namespace App\Services;

use App\Enums\CustomerRequests\Source;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Support\CustomerRequests\CustomerRequestMessages;
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

    public function todayCount(Customer $customer, ?CarbonImmutable $now = null): int
    {
        [$start, $end] = $this->todayUtcRange($now);

        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->whereIn('source', [Source::Web, Source::WhatsApp])
            ->whereBetween('created_at', [$start, $end])
            ->count();
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
