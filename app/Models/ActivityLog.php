<?php

namespace App\Models;

use App\Enums\ActivityLogs\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const SourceUser = 'user';

    public const SourceSystem = 'system';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'event' => Event::class,
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
