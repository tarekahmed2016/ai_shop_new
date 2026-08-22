<?php

namespace App\Models;

use Database\Factories\MerchantOfferImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['original_name', 'mime_type', 'size', 'sort_order'])]
#[Hidden(['path'])]
class MerchantOfferImage extends Model
{
    /** @use HasFactory<MerchantOfferImageFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const MAX_PER_OFFER = 5;

    /**
     * @return BelongsTo<MerchantOffer, $this>
     */
    public function merchantOffer(): BelongsTo
    {
        return $this->belongsTo(MerchantOffer::class);
    }
}
