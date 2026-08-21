<?php

namespace App\Models;

use Database\Factories\RequestImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['original_name', 'mime_type', 'size'])]
#[Hidden(['path'])]
class RequestImage extends Model
{
    /** @use HasFactory<RequestImageFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}
