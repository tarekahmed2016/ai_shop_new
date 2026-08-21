<?php

namespace App\Models;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use Database\Factories\MerchantUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['merchant_id', 'user_id', 'role', 'status'])]
class MerchantUser extends Model
{
    /** @use HasFactory<MerchantUserFactory> */
    use HasFactory;

    protected $table = 'merchant_user';

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted', 'role_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'role' => Role::class,
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
    protected function roleFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role === null ? null : [
                'value' => $this->role->value,
                'label' => $this->role->label(),
                'name' => $this->role->name,
            ]
        );
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MerchantUserPermission, $this>
     */
    public function permissionAssignments(): HasMany
    {
        return $this->hasMany(MerchantUserPermission::class, 'merchant_user_id');
    }

    /**
     * @return BelongsToMany<MerchantPermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(MerchantPermission::class, 'merchant_user_permissions')
            ->withTimestamps();
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
