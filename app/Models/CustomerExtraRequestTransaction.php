<?php

namespace App\Models;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerExtraRequests\TransactionType;
use Database\Factories\CustomerExtraRequestTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'type',
    'amount',
    'source',
    'payment_transaction_id',
    'reference',
    'notes',
    'created_by_user_id',
    'customer_request_id',
])]
class CustomerExtraRequestTransaction extends Model
{
    /** @use HasFactory<CustomerExtraRequestTransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['type_formatted', 'source_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'amount' => 'integer',
            'customer_id' => 'integer',
            'payment_transaction_id' => 'integer',
            'created_by_user_id' => 'integer',
            'customer_request_id' => 'integer',
        ];
    }

    /**
     * @return Attribute<array{value: int, label: string, name: string}|null, never>
     */
    protected function typeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type === null ? null : [
                'value' => $this->type->value,
                'label' => $this->type->label(),
                'name' => $this->type->name,
            ]
        );
    }

    /**
     * @return Attribute<array{value: int, label: string, name: string}|null, never>
     */
    protected function sourceFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->source === null ? null : [
                'value' => $this->source->value,
                'label' => $this->source->label(),
                'name' => $this->source->name,
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
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}
