<?php

namespace App\Services;

use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Support\MerchantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MerchantContextService
{
    public const SESSION_KEY = 'merchant_context_id';

    public function __construct(
        public MerchantContext $merchantContext,
    ) {}

    /**
     * @return list<array{public_id: string, name: string, role: string}>
     */
    public function availableMerchantsFor(User $user): array
    {
        return $user->merchantMemberships()
            ->with('merchant')
            ->get()
            ->filter(fn (MerchantUser $membership) => $membership->isActive()
                && $membership->merchant
                && $membership->merchant->isActive())
            ->map(fn (MerchantUser $membership) => [
                'public_id' => $membership->merchant->public_id,
                'name' => $membership->merchant->name,
                'role' => $membership->role->value,
            ])
            ->values()
            ->all();
    }

    public function activateByPublicId(User $user, string $publicId, Request $request): Merchant
    {
        $merchant = Merchant::query()->where('public_id', $publicId)->first();

        if (! $merchant) {
            throw new AccessDeniedHttpException;
        }

        $this->assertCanActivate($user, $merchant);

        $membership = $this->activeMembership($user, $merchant);

        $request->session()->put(self::SESSION_KEY, $merchant->id);
        $this->merchantContext->set($merchant, $membership);

        return $merchant;
    }

    public function establishFromSession(User $user, Request $request): bool
    {
        $merchantId = $request->session()->get(self::SESSION_KEY);

        if (! is_numeric($merchantId)) {
            $this->clear($request);

            return false;
        }

        $merchant = Merchant::query()->find((int) $merchantId);

        if (! $merchant) {
            $this->clear($request);

            return false;
        }

        try {
            $this->assertCanActivate($user, $merchant);
        } catch (AccessDeniedHttpException) {
            $this->clear($request);

            return false;
        }

        $membership = $this->activeMembership($user, $merchant);
        $this->merchantContext->set($merchant, $membership);

        return true;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $this->merchantContext->clear();
    }

    public function assertCanActivate(User $user, Merchant $merchant): void
    {
        if (! $merchant->isActive()) {
            throw new AccessDeniedHttpException;
        }

        $membership = $this->activeMembership($user, $merchant);

        if (! $membership) {
            throw new AccessDeniedHttpException;
        }
    }

    public function activeMembership(User $user, Merchant $merchant): ?MerchantUser
    {
        return MerchantUser::query()
            ->where('merchant_id', $merchant->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active)
            ->first();
    }
}
