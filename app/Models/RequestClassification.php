<?php

namespace App\Models;

use App\Enums\RequestClassifications\Status;
use Database\Factories\RequestClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'model',
    'detected_item',
    'suggested_category_id',
    'confidence',
    'alternatives',
    'needs_more_information',
    'question',
    'reason',
    'status',
    'customer_confirmed_category_id',
    'confirmed_at',
    'input_has_image',
    'provider_response_id',
    'input_tokens',
    'cached_input_tokens',
    'output_tokens',
    'reasoning_tokens',
    'total_tokens',
])]
class RequestClassification extends Model
{
    /** @use HasFactory<RequestClassificationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'confidence' => 'float',
            'alternatives' => 'array',
            'needs_more_information' => 'boolean',
            'input_has_image' => 'boolean',
            'confirmed_at' => 'datetime',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function confirmedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'customer_confirmed_category_id');
    }
}
