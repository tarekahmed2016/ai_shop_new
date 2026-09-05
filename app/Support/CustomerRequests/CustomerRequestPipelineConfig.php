<?php

namespace App\Support\CustomerRequests;

use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Single source of runtime reads for async-pipeline knobs. Defaults live
 * only in config/customer_requests.php (and classification.async_enabled);
 * callers must not pass hardcoded fallbacks to config().
 */
final class CustomerRequestPipelineConfig
{
    public static function asyncEnabled(): bool
    {
        return (bool) config('classification.async_enabled');
    }

    public static function aiQueue(): string
    {
        return self::nonEmptyString('customer_requests.ai_queue');
    }

    public static function maxOpenAiAttempts(): int
    {
        return self::positiveInt('customer_requests.max_open_ai_attempts');
    }

    public static function openAttemptTtlHours(): int
    {
        return self::positiveInt('customer_requests.open_attempt_ttl_hours');
    }

    public static function stuckAiThresholdMinutes(): int
    {
        return self::positiveInt('customer_requests.stuck_ai_threshold_minutes');
    }

    public static function stuckAiMaxRecoveryAttempts(): int
    {
        return self::positiveInt('customer_requests.stuck_ai_max_recovery_attempts');
    }

    public static function stuckAiRecoveryEveryMinutes(): int
    {
        return self::positiveInt('customer_requests.stuck_ai_recovery_every_minutes');
    }

    public static function matchingRecoveryStaleMinutes(): int
    {
        return self::positiveInt('customer_requests.matching_recovery_stale_minutes');
    }

    public static function matchingRecoveryEveryMinutes(): int
    {
        return self::positiveInt('customer_requests.matching_recovery_every_minutes');
    }

    public static function statusPollIntervalMs(): int
    {
        return self::positiveInt('customer_requests.status_poll_interval_ms');
    }

    public static function statusPollTimeoutMs(): int
    {
        return self::positiveInt('customer_requests.status_poll_timeout_ms');
    }

    public static function statusPollRetryMs(): int
    {
        return self::positiveInt('customer_requests.status_poll_retry_ms');
    }

    public static function duplicateNoticeTtlSeconds(): int
    {
        return self::positiveInt('customer_requests.duplicate_notice_ttl_seconds');
    }

    /**
     * @return list<mixed>
     */
    public static function submissionTokenRules(): array
    {
        return [
            Rule::requiredIf(fn () => self::asyncEnabled()),
            'nullable',
            'string',
            'size:26',
            'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/',
        ];
    }

    private static function nonEmptyString(string $key): string
    {
        $value = config($key);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Missing required config [{$key}].");
        }

        return $value;
    }

    private static function positiveInt(string $key): int
    {
        $value = (int) config($key);
        if ($value < 1) {
            throw new InvalidArgumentException("Config [{$key}] must be a positive integer.");
        }

        return $value;
    }
}
