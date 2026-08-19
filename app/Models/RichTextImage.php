<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RichTextImage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by',
        'disk',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['url'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => asset('storage/'.$this->path),
        );
    }
}
