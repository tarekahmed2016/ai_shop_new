<?php

namespace App\Models;

use Database\Factories\PlatformSettingChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key',
    'old_value',
    'new_value',
    'notes',
    'changed_by_user_id',
])]
class PlatformSettingChange extends Model
{
    /** @use HasFactory<PlatformSettingChangeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
