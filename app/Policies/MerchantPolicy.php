<?php

namespace App\Policies;

use App\Enums\MerchantMemberships\Status;
use App\Enums\MerchantOfferCredits\AdminPermission;
use App\Models\Merchant;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Support\AdminAccess;

class MerchantPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'merchants.view');
    }

    public function view(User $user, Merchant $merchant): bool
    {
        if (AdminAccess::allows($user, 'merchants.view')) {
            return true;
        }

        return $user->merchantMemberships()
            ->where('merchant_id', $merchant->id)
            ->where('status', Status::Active)
            ->exists();
    }

    public function create(User $user): bool
    {
        return AdminAccess::allows($user, 'merchants.create');
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return AdminAccess::allows($user, 'merchants.update');
    }

    public function viewCredits(User $user, Merchant $merchant): bool
    {
        return $this->hasCreditPermission($user, AdminPermission::View);
    }

    public function viewCreditHistory(User $user): bool
    {
        return $this->hasCreditPermission($user, AdminPermission::View);
    }

    public function addCredits(User $user, Merchant $merchant): bool
    {
        return $this->hasCreditPermission($user, AdminPermission::Add);
    }

    public function deductCredits(User $user, Merchant $merchant): bool
    {
        return $this->hasCreditPermission($user, AdminPermission::Deduct);
    }

    public function bulkAddCredits(User $user): bool
    {
        return $this->hasCreditPermission($user, AdminPermission::Add);
    }

    public function manageCreditSettings(User $user): bool
    {
        return $this->hasCreditPermission($user, AdminPermission::ManageSettings);
    }

    public function select(User $user, Merchant $merchant): bool
    {
        return app(MerchantContextService::class)
            ->activeMembership($user, $merchant) !== null
            && $merchant->isActive();
    }

    private function hasCreditPermission(User $user, AdminPermission $permission): bool
    {
        return $user->hasRole('admin') && $user->can($permission->value);
    }
}
