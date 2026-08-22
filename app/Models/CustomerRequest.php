<?php

namespace App\Models;

use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status;
use Database\Factories\CustomerRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['request_text', 'status', 'category_id'])]
class CustomerRequest extends Model
{
    /** @use HasFactory<CustomerRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted', 'source_formatted', 'has_image', 'image_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'source' => Source::class,
            'category_id' => 'integer',
            'customer_id' => 'integer',
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

    /**
     * @return Attribute<array{value: string, label: string, name: string}|null, never>
     */
    protected function sourceFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->source === null ? null : [
                'value' => $this->source->value,
                'label' => $this->source->label(),
                'name' => $this->source->name,
            ]
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function hasImage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->relationLoaded('image') ? $this->image !== null : $this->image()->exists()
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->relationLoaded('image') || $this->image === null || $this->public_id === null) {
                    return null;
                }

                return route('customer-requests.image', $this);
            }
        );
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasOne<RequestImage, $this>
     */
    public function image(): HasOne
    {
        return $this->hasOne(RequestImage::class);
    }

    /**
     * @return HasMany<RequestMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(RequestMatch::class);
    }

    /**
     * @return HasMany<MerchantOffer, $this>
     */
    public function merchantOffers(): HasMany
    {
        return $this->hasMany(MerchantOffer::class);
    }

    /**
     * @return HasMany<MerchantOffer, $this>
     */
    public function submittedOffers(): HasMany
    {
        return $this->hasMany(MerchantOffer::class)->submitted();
    }

    public function isMatchable(): bool
    {
        if ($this->category_id === null) {
            return false;
        }

        if ($this->status === Status::Closed || $this->status === Status::Cancelled) {
            return false;
        }

        $category = $this->relationLoaded('category') ? $this->category : $this->category()->first();

        return $category !== null && $category->isActive();
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
