<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_user_id', 'merchant_permission_id'])]
class MerchantUserPermission extends Model
{
    /**
     * @return BelongsTo<MerchantUser, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(MerchantUser::class, 'merchant_user_id');
    }

    /**
     * @return BelongsTo<MerchantPermission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(MerchantPermission::class, 'merchant_permission_id');
    }
}
