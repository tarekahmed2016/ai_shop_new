<?php

namespace App\Models;

use App\Enums\Customers\Status;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['user_id', 'name', 'phone', 'whatsapp_id', 'email', 'status'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted', 'display_name'];

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

    /**
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name ?: ($this->phone ?: ($this->email ?: 'Customer #'.$this->id))
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CustomerRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(CustomerRequest::class);
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
