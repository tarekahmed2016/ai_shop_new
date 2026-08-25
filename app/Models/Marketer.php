<?php

namespace App\Models;

use App\Enums\Marketers\Status;
use App\Support\ReferralCode;
use Database\Factories\MarketerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'referral_code',
    'status',
    'customer_commission_rate',
    'merchant_commission_rate',
])]
class Marketer extends Model
{
    /** @use HasFactory<MarketerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted'];

    protected static function booted(): void
    {
        static::creating(function (Marketer $marketer): void {
            if (empty($marketer->public_id)) {
                $marketer->public_id = (string) Str::ulid();
            }

            $normalized = ReferralCode::normalize((string) $marketer->referral_code);
            if ($normalized !== null) {
                $marketer->referral_code = $normalized;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'customer_commission_rate' => 'decimal:3',
            'merchant_commission_rate' => 'decimal:3',
        ];
    }

    /**
     * @return Attribute<array{value: int, label: string, name: string}|null, never>
     */
    protected function statusFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === null ? null : [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'name' => $this->status->name,
            ]
        );
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    public function isPending(): bool
    {
        return $this->status === Status::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === Status::Rejected;
    }

    public function isInactive(): bool
    {
        return $this->status === Status::Inactive;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MarketerReferral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(MarketerReferral::class);
    }

    /**
     * @return HasMany<MarketerCommission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(MarketerCommission::class);
    }

    /**
     * @return HasMany<MarketerPayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(MarketerPayout::class);
    }

    /**
     * Database-side referral totals for the admin index.
     * Counts referred users, not membership rows.
     *
     * @param  Builder<Marketer>  $query
     * @return Builder<Marketer>
     */
    public function scopeWithReferralCapabilityCounts(Builder $query): Builder
    {
        return $query->withCount([
            'referrals',
            'referrals as customer_count' => fn (Builder $referrals) => $referrals->whereReferredHasActiveCustomer(),
            'referrals as merchant_count' => fn (Builder $referrals) => $referrals->whereReferredHasActiveMerchant(),
            'referrals as dual_count' => fn (Builder $referrals) => $referrals->whereReferredHasDualCapability(),
        ]);
    }
}
