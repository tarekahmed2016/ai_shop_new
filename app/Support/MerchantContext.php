<?php

namespace App\Support;

use App\Enums\MerchantMemberships\Role;
use App\Models\Merchant;
use App\Models\MerchantUser;

class MerchantContext
{
    private ?Merchant $merchant = null;

    private ?MerchantUser $membership = null;

    public function set(Merchant $merchant, MerchantUser $membership): void
    {
        $this->merchant = $merchant;
        $this->membership = $membership;
    }

    public function clear(): void
    {
        $this->merchant = null;
        $this->membership = null;
    }

    public function has(): bool
    {
        return $this->merchant !== null && $this->membership !== null;
    }

    public function isActive(): bool
    {
        return $this->has()
            && $this->merchant->isActive()
            && $this->membership->isActive();
    }

    public function merchant(): ?Merchant
    {
        return $this->merchant;
    }

    public function membership(): ?MerchantUser
    {
        return $this->membership;
    }

    public function merchantId(): ?int
    {
        return $this->merchant?->id;
    }

    public function publicId(): ?string
    {
        return $this->merchant?->public_id;
    }

    public function role(): ?Role
    {
        return $this->membership?->role;
    }

    /**
     * @return array{public_id: string, name: string, role: string}|null
     */
    public function toArray(): ?array
    {
        if (! $this->has()) {
            return null;
        }

        return [
            'public_id' => $this->merchant->public_id,
            'name' => $this->merchant->name,
            'role' => $this->membership->role->value,
        ];
    }
}
