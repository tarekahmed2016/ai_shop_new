<?php

namespace App\Models;

use App\Enums\Users\Status;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable;

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * @return HasOne<Marketer, $this>
     */
    public function marketer(): HasOne
    {
        return $this->hasOne(Marketer::class);
    }

    /**
     * @return HasOne<MarketerReferral, $this>
     */
    public function marketerReferral(): HasOne
    {
        return $this->hasOne(MarketerReferral::class, 'referred_user_id');
    }

    /**
     * @return HasMany<MerchantUser, $this>
     */
    public function merchantMemberships(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    /**
     * @return BelongsToMany<Merchant, $this>
     */
    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class, 'merchant_user')
            ->withPivot(['id', 'role', 'status'])
            ->withTimestamps();
    }
}
