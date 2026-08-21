<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;

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

    public function customerId(): ?int
    {
        return $this->customer?->id;
    }

    /**
     * @return array{public_id: string, name: string|null, email: string|null, phone: string|null}|null
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
        ];
    }

    public function resolveFromUser(?User $user): ?Customer
    {
        if ($user === null) {
            $this->clear();

            return null;
        }

        $customer = $user->customer;

        if ($customer === null) {
            $this->clear();

            return null;
        }

        $this->set($customer);

        return $customer;
    }
}
