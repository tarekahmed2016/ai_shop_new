<?php

namespace App\Services;

use App\Models\PlatformSetting;

class PlatformSettingService
{
    /**
     * @var array<string, string|null>
     */
    private array $runtimeCache = [];

    public function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->runtimeCache)) {
            return $this->runtimeCache[$key];
        }

        $value = PlatformSetting::query()->where('key', $key)->value('value');
        $resolved = $value === null ? $default : (string) $value;
        $this->runtimeCache[$key] = $resolved;

        return $resolved;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function set(string $key, string $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        $this->runtimeCache[$key] = $value;
    }

    public function forget(?string $key = null): void
    {
        if ($key === null) {
            $this->runtimeCache = [];

            return;
        }

        unset($this->runtimeCache[$key]);
    }

    public function isOfferCreditEnforcementEnabled(): bool
    {
        return $this->boolean(PlatformSetting::KEY_OFFER_CREDIT_ENFORCEMENT, false);
    }

    public function setOfferCreditEnforcementEnabled(bool $enabled): void
    {
        $this->set(PlatformSetting::KEY_OFFER_CREDIT_ENFORCEMENT, $enabled ? '1' : '0');
    }

    public function dailyCustomerRequestLimit(): int
    {
        $max = max(1, (int) config('customer_requests.max_daily_limit', 100));
        $default = max(1, min($max, (int) config('customer_requests.default_daily_limit', 3)));
        $value = $this->get(PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT);

        if ($value === null || ! is_numeric($value)) {
            return $default;
        }

        return max(1, min($max, (int) $value));
    }

    public function setDailyCustomerRequestLimit(int $limit): void
    {
        $max = max(1, (int) config('customer_requests.max_daily_limit', 100));
        $this->set(
            PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT,
            (string) max(1, min($max, $limit)),
        );
    }
}
