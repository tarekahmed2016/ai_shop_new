<?php

namespace App\Services;

use App\Enums\CustomerDailyRequestLimitChanges\ChangeType;
use App\Models\Customer;
use App\Models\CustomerDailyRequestLimitChange;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingChange;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerDailyRequestLimitAuditService
{
    public function __construct(
        public CustomerRequestLimitService $customerRequestLimitService,
    ) {}

    public function recordOverrideChange(
        Customer $customer,
        mixed $oldOverride,
        mixed $newOverride,
        ?User $actor,
        ?string $notes = null,
    ): ?CustomerDailyRequestLimitChange {
        $old = $this->nullableInt($oldOverride);
        $new = $this->nullableInt($newOverride);

        if ($old === $new) {
            return null;
        }

        $global = $this->customerRequestLimitService->globalLimit();

        return CustomerDailyRequestLimitChange::query()->create([
            'customer_id' => $customer->id,
            'old_override' => $old,
            'new_override' => $new,
            'effective_global_limit' => $global,
            'old_effective_limit' => $old ?? $global,
            'new_effective_limit' => $new ?? $global,
            'change_type' => $this->changeType($old, $new),
            'notes' => $this->nullableNotes($notes),
            'changed_by_user_id' => $actor?->id,
        ]);
    }

    public function recordGlobalLimitChange(
        int $oldLimit,
        int $newLimit,
        ?User $actor,
        ?string $notes = null,
    ): ?PlatformSettingChange {
        if ($oldLimit === $newLimit) {
            return null;
        }

        return PlatformSettingChange::query()->create([
            'key' => PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT,
            'old_value' => (string) $oldLimit,
            'new_value' => (string) $newLimit,
            'notes' => $this->nullableNotes($notes),
            'changed_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, CustomerDailyRequestLimitChange>
     */
    public function paginatedForCustomer(Customer $customer, int $perPage = 25): LengthAwarePaginator
    {
        return CustomerDailyRequestLimitChange::query()
            ->where('customer_id', $customer->id)
            ->with(['changedBy:id,name,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, PlatformSettingChange>
     */
    public function paginatedGlobalLimitChanges(int $perPage = 25, string $pageName = 'global_limit_page'): LengthAwarePaginator
    {
        return PlatformSettingChange::query()
            ->where('key', PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT)
            ->with(['changedBy:id,name,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    public function presentChange(CustomerDailyRequestLimitChange $change): array
    {
        $changedBy = $change->changedBy;

        return [
            'id' => $change->id,
            'created_at' => $change->created_at,
            'old_override' => $change->old_override === null ? null : (int) $change->old_override,
            'new_override' => $change->new_override === null ? null : (int) $change->new_override,
            'effective_global_limit' => $change->effective_global_limit,
            'old_effective_limit' => $change->old_effective_limit,
            'new_effective_limit' => $change->new_effective_limit,
            'change_type' => $change->change_type?->value,
            'change_type_formatted' => $change->change_type_formatted,
            'notes' => $change->notes,
            'changed_by' => $changedBy === null ? null : [
                'id' => $changedBy->id,
                'name' => $changedBy->name,
                'email' => $changedBy->email,
            ],
        ];
    }

    public function presentGlobalChange(PlatformSettingChange $change): array
    {
        $changedBy = $change->changedBy;

        return [
            'id' => $change->id,
            'created_at' => $change->created_at,
            'old_value' => $change->old_value,
            'new_value' => $change->new_value,
            'notes' => $change->notes,
            'changed_by' => $changedBy === null ? null : [
                'id' => $changedBy->id,
                'name' => $changedBy->name,
                'email' => $changedBy->email,
            ],
        ];
    }

    private function changeType(?int $old, ?int $new): ChangeType
    {
        if ($old === null && $new !== null) {
            return ChangeType::SetOverride;
        }

        if ($old !== null && $new === null) {
            return ChangeType::ClearOverride;
        }

        return ChangeType::UpdateOverride;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $trimmed = trim($notes);

        return $trimmed === '' ? null : $trimmed;
    }
}
