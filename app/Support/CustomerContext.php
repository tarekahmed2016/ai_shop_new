<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerRequestLimitService;

class CustomerContext
{
    private ?Customer $customer = null;

    public function set(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function clear(): void
    {
        $this->customer = null;
    }

    public function customer(): ?Customer
    {
        return $this->customer;
    }

    public function has(): bool
    {
        return $this->customer !== null;
    }

    public function isActive(): bool
    {
        return $this->has() && $this->customer->isActive();
    }

    public function canUsePortal(): bool
    {
        return $this->has() && $this->customer->canUsePortal();
    }

    public function customerId(): ?int
    {
        return $this->customer?->id;
    }

    /**
     * @return array{
     *     public_id: string,
     *     name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     status: string|null,
     *     is_suspended: bool,
     *     suspension_reason: string|null,
     *     suspended_at: mixed,
     *     suspension_types: list<string>,
     *     request_quota: array<string, mixed>
     * }|null
     */
    public function toArray(): ?array
    {
        if (! $this->has()) {
            return null;
        }

        return [
            'public_id' => $this->customer->public_id,
            'name' => $this->customer->name,
            'email' => $this->customer->email,
            'phone' => $this->customer->phone,
            'status' => $this->customer->status?->name,
            'is_suspended' => $this->customer->isSuspended(),
            'suspension_reason' => $this->customer->suspension_reason,
            'suspended_at' => $this->customer->suspended_at,
            'suspension_types' => $this->customer->suspension_types ?? [],
            'request_quota' => app(CustomerRequestLimitService::class)->snapshot($this->customer),
        ];
    }

    public function resolveFromUser(?User $user): ?Customer
    {
        if ($user === null) {
            $this->clear();

            return null;
        }

        $user->unsetRelation('customer');
        $customer = $user->customer;

        if ($customer === null) {
            $this->clear();

            return null;
        }

        $this->set($customer);

        return $customer;
    }
}
