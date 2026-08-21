<?php

namespace App\Models;

use Database\Factories\MerchantPermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name_ar', 'name_en', 'group_key'])]
class MerchantPermission extends Model
{
    /** @use HasFactory<MerchantPermissionFactory> */
    use HasFactory;

    /**
     * @return HasMany<MerchantUserPermission, $this>
     */
    public function membershipAssignments(): HasMany
    {
        return $this->hasMany(MerchantUserPermission::class);
    }

    /**
     * @return BelongsToMany<MerchantUser, $this>
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(MerchantUser::class, 'merchant_user_permissions')
            ->withTimestamps();
    }
}
