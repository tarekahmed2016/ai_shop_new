<?php

namespace App\Models;

use Database\Factories\MerchantRequestMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'customer_request_id', 'matched_category_id', 'matched_at'])]
class MerchantRequestMatch extends Model
{
    /** @use HasFactory<MerchantRequestMatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'customer_request_id' => 'integer',
            'matched_category_id' => 'integer',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function matchedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'matched_category_id');
    }
}
