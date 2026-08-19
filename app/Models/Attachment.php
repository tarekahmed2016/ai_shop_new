<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['name', 'path', 'collection', 'attachable_type', 'attachable_id'])]
class Attachment extends Model
{
    /**
     * @var list<string>
     */
    protected $appends = ['asset_path'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function assetPath(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes) {
                $path = $attributes['path'] ?? null;

                return $path ? asset('storage/'.$attributes['path']) : null;
            }
        );
    }
}
