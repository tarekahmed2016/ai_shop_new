<?php

namespace App\Models;

use App\Enums\Merchants\Status;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
