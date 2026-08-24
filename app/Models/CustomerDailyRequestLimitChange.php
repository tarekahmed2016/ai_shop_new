<?php

namespace App\Models;

use App\Enums\CustomerDailyRequestLimitChanges\ChangeType;
use Database\Factories\CustomerDailyRequestLimitChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'old_override',
    'new_override',
    'effective_global_limit',
    'old_effective_limit',
    'new_effective_limit',
    'change_type',
    'notes',
    'changed_by_user_id',
])]
class CustomerDailyRequestLimitChange extends Model
{
    /** @use HasFactory<CustomerDailyRequestLimitChangeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['change_type_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_type' => ChangeType::class,
            'effective_global_limit' => 'integer',
            'old_effective_limit' => 'integer',
            'new_effective_limit' => 'integer',
        ];
    }

    /**
     * @return Attribute<array{value: string, label: string, name: string}|null, never>
     */
    protected function changeTypeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->change_type === null ? null : [
                'value' => $this->change_type->value,
                'label' => $this->change_type->label(),
                'name' => $this->change_type->name,
            ]
        );
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
