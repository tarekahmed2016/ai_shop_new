<?php

namespace App\Models;

use App\Enums\MerchantMemberships\Role;
use App\Enums\Merchants\Status;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'phone', 'email', 'status'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
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

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return HasMany<MerchantUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    /**
     * First owner membership for display fallback. Staff is never used.
     *
     * @return HasOne<MerchantUser, $this>
     */
    public function ownerMembership(): HasOne
    {
        return $this->hasOne(MerchantUser::class)->ofMany(
            ['id' => 'min'],
            function (Builder $query) {
                $query->where('role', Role::Owner);
            }
        );
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'merchant_user')
            ->withPivot(['id', 'role', 'status'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'merchant_categories')
            ->withPivot(['whatsapp_phone'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<MerchantCategory, $this>
     */
    public function categoryAssignments(): HasMany
    {
        return $this->hasMany(MerchantCategory::class);
    }

    /**
     * @return HasMany<RequestMatch, $this>
     */
    public function requestMatches(): HasMany
    {
        return $this->hasMany(RequestMatch::class);
    }

    /**
     * Permanent eligibility history. Not deleted when live matches are removed.
     *
     * @return HasMany<MerchantRequestMatch, $this>
     */
    public function receivedRequestMatches(): HasMany
    {
        return $this->hasMany(MerchantRequestMatch::class);
    }

    /**
     * @return HasMany<MerchantOffer, $this>
     */
    public function merchantOffers(): HasMany
    {
        return $this->hasMany(MerchantOffer::class);
    }

    /**
     * @return HasMany<CustomerOfferContactReveal, $this>
     */
    public function offerContactReveals(): HasMany
    {
        return $this->hasMany(CustomerOfferContactReveal::class);
    }

    /**
     * @return HasMany<MerchantOfferCreditTransaction, $this>
     */
    public function offerCreditTransactions(): HasMany
    {
        return $this->hasMany(MerchantOfferCreditTransaction::class);
    }

    /**
     * Database-side usage totals for the admin merchants index.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeWithUsageCounts(Builder $query): Builder
    {
        return $query->withCount([
            'receivedRequestMatches as requests_received_count',
            'merchantOffers as offers_submitted_count' => fn (Builder $offers) => $offers->forTrackedSubmittedResponse(),
        ]);
    }

    /**
     * Ledger-derived remaining credits. Null sum becomes 0 for merchants with no rows.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeWithCreditBalance(Builder $query): Builder
    {
        return $query->withSum('offerCreditTransactions as offer_credit_balance', 'amount');
    }

    /**
     * Whole-number percentage from already-loaded usage counts. Not capped at 100.
     */
    public function offerSubmissionRate(): int
    {
        $received = (int) ($this->requests_received_count ?? 0);
        if ($received === 0) {
            return 0;
        }

        return (int) round(((int) ($this->offers_submitted_count ?? 0)) / $received * 100);
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
