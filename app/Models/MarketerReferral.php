<?php

namespace App\Models;

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use Database\Factories\MarketerReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marketer_id', 'referred_user_id', 'referral_code', 'landing_path', 'registered_at'])]
class MarketerReferral extends Model
{
    /** @use HasFactory<MarketerReferralFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
        ];
    }

    /**
     * Referred user has an ACTIVE Customer capability.
     *
     * @param  Builder<MarketerReferral>  $query
     * @return Builder<MarketerReferral>
     */
    public function scopeWhereReferredHasActiveCustomer(Builder $query): Builder
    {
        return $query->whereHas('referredUser.customer', function (Builder $customerQuery) {
            $customerQuery->where('status', CustomerStatus::Active);
        });
    }

    /**
     * Referred user has at least one ACTIVE membership on an ACTIVE merchant.
     * Owner-only association is not enough; inactive memberships and inactive merchants are excluded.
     *
     * @param  Builder<MarketerReferral>  $query
     * @return Builder<MarketerReferral>
     */
    public function scopeWhereReferredHasActiveMerchant(Builder $query): Builder
    {
        return $query->whereHas('referredUser.merchantMemberships', function (Builder $membershipQuery) {
            $membershipQuery
                ->where('status', MembershipStatus::Active)
                ->whereHas('merchant', function (Builder $merchantQuery) {
                    $merchantQuery->where('status', MerchantStatus::Active);
                });
        });
    }

    /**
     * Referred user satisfies both active Customer and active Merchant capability.
     *
     * @param  Builder<MarketerReferral>  $query
     * @return Builder<MarketerReferral>
     */
    public function scopeWhereReferredHasDualCapability(Builder $query): Builder
    {
        return $query->whereReferredHasActiveCustomer()->whereReferredHasActiveMerchant();
    }

    /**
     * @return BelongsTo<Marketer, $this>
     */
    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
