<?php

namespace App\Services\CustomerRequests;

use App\Models\CustomerRequest;
use App\Support\CustomerRequests\CustomerRequestMessages;
use App\Support\CustomerRequests\CustomerRequestPipelineConfig;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Short-lived cache copy of a duplicate-block outcome. The pending row
 * itself is kept (ai_stage=DuplicateBlocked) so the customer can always
 * reload the show page. The cache covers the race where a poll lands
 * after a later delete/expiry of that row.
 *
 * Cache is transport-only, not a system of record. If the entry is
 * missing, expired, or the cache store fails, callers must fall back to
 * a generic "no longer available" payload rather than 404.
 */
class CustomerRequestDuplicateNoticeService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(CustomerRequest $blocked, CustomerRequest $matched): array
    {
        return [
            'request_public_id' => $blocked->public_id,
            'status' => $blocked->status?->name ?? 'Cancelled',
            'ai_stage' => 'duplicate_blocked',
            'poll' => false,
            'poll_interval_ms' => CustomerRequestPipelineConfig::statusPollIntervalMs(),
            'poll_timeout_ms' => CustomerRequestPipelineConfig::statusPollTimeoutMs(),
            'message' => CustomerRequestMessages::duplicateRequest(),
            'classification' => null,
            'duplicate_of_request_public_id' => $matched->public_id,
            'suspended' => false,
            'quota_exhausted' => false,
        ];
    }

    public function remember(CustomerRequest $blocked, CustomerRequest $matched): void
    {
        $ttl = CustomerRequestPipelineConfig::duplicateNoticeTtlSeconds();
        $payload = $this->payload($blocked, $matched);

        try {
            Cache::put($this->key((int) $blocked->customer_id, (string) $blocked->public_id), $payload, $ttl);
            Cache::put($this->key((int) $blocked->customer_id, (string) $blocked->id), $payload, $ttl);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $customerId, string $publicId): ?array
    {
        try {
            $cached = Cache::get($this->key($customerId, $publicId));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return is_array($cached) ? $cached : null;
    }

    /**
     * Status payload for a public_id whose row is gone: cached duplicate
     * notice if still present, otherwise a generic graceful missing body.
     *
     * @return array<string, mixed>
     */
    public function resolveForMissingRow(int $customerId, string $publicId): array
    {
        return $this->find($customerId, $publicId) ?? $this->missing($publicId);
    }

    /**
     * Generic graceful payload when neither the row nor the cache notice
     * exists — still better than an unexplained 404 for a public_id the
     * customer was actively polling.
     *
     * @return array<string, mixed>
     */
    public function missing(string $publicId): array
    {
        return [
            'request_public_id' => $publicId,
            'status' => null,
            'ai_stage' => null,
            'poll' => false,
            'poll_interval_ms' => CustomerRequestPipelineConfig::statusPollIntervalMs(),
            'poll_timeout_ms' => CustomerRequestPipelineConfig::statusPollTimeoutMs(),
            'message' => CustomerRequestMessages::requestNoLongerAvailable(),
            'classification' => null,
            'duplicate_of_request_public_id' => null,
            'suspended' => false,
            'quota_exhausted' => false,
        ];
    }

    private function key(int $customerId, string $identifier): string
    {
        return 'customer-request-duplicate-notice:'.$customerId.':'.$identifier;
    }
}
