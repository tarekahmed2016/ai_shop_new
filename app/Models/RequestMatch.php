<?php

namespace App\Models;

use App\Enums\RequestMatches\Status;
use Database\Factories\RequestMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['status'])]
class RequestMatch extends Model
{
    /** @use HasFactory<RequestMatchFactory> */
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
            'customer_request_id' => 'integer',
            'merchant_id' => 'integer',
            'matched_at' => 'datetime',
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
     * @param  Builder<RequestMatch>  $query
     * @return Builder<RequestMatch>
     */
    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * @param  Builder<RequestMatch>  $query
     * @return Builder<RequestMatch>
     */
    public function scopeVisibleToMerchant(Builder $query): Builder
    {
        return $query->whereIn('status', [Status::Pending, Status::Viewed]);
    }

    public function isVisibleToMerchant(): bool
    {
        return $this->status instanceof Status && $this->status->isVisibleToMerchant();
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
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
