<?php

namespace App\Models;

use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status;
use Database\Factories\MerchantOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['price', 'availability_status', 'notes', 'valid_until', 'status'])]
class MerchantOffer extends Model
{
    /** @use HasFactory<MerchantOfferFactory> */
    use HasFactory;

    public const CURRENCY = 'OMR';

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted', 'availability_status_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'availability_status' => AvailabilityStatus::class,
            'status' => Status::class,
            'valid_until' => 'date',
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'customer_request_id' => 'integer',
            'merchant_id' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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

    /**
     * @return Attribute<array{value: int, label: string, name: string}|null, never>
     */
    protected function availabilityStatusFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->availability_status === null ? null : [
                'value' => $this->availability_status->value,
                'label' => $this->availability_status->label(),
                'name' => $this->availability_status->name,
            ]
        );
    }

    /**
     * @param  Builder<MerchantOffer>  $query
     * @return Builder<MerchantOffer>
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', Status::Submitted);
    }

    /**
     * Current submitted responses on requests historically matched to the same merchant.
     *
     * @param  Builder<MerchantOffer>  $query
     * @return Builder<MerchantOffer>
     */
    public function scopeForTrackedSubmittedResponse(Builder $query): Builder
    {
        return $query
            ->where('status', Status::Submitted)
            ->whereExists(function ($exists) {
                $exists->selectRaw('1')
                    ->from('merchant_request_matches')
                    ->whereColumn('merchant_request_matches.merchant_id', 'merchant_offers.merchant_id')
                    ->whereColumn('merchant_request_matches.customer_request_id', 'merchant_offers.customer_request_id');
            });
    }

    /**
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasMany<MerchantOfferImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(MerchantOfferImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CustomerOfferContactReveal, $this>
     */
    public function contactReveals(): HasMany
    {
        return $this->hasMany(CustomerOfferContactReveal::class);
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
