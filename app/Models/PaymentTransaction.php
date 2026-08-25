<?php

namespace App\Models;

use App\Enums\Payments\Method;
use App\Enums\Payments\Status;
use App\Enums\Payments\Type;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'public_id',
    'payer_user_id',
    'type',
    'amount',
    'status',
    'payment_method',
    'reference',
    'notes',
    'paid_at',
    'created_by_user_id',
    'related_customer_id',
    'related_merchant_id',
])]
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['type_formatted', 'status_formatted', 'payment_method_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => Type::class,
            'status' => Status::class,
            'payment_method' => Method::class,
            'amount' => 'decimal:3',
            'paid_at' => 'datetime',
            'payer_user_id' => 'integer',
            'created_by_user_id' => 'integer',
            'related_customer_id' => 'integer',
            'related_merchant_id' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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
     * @return Attribute<array{value: int, label: string, name: string}|null, never>
     */
    protected function paymentMethodFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_method === null ? null : [
                'value' => $this->payment_method->value,
                'label' => $this->payment_method->label(),
                'name' => $this->payment_method->name,
            ]
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function relatedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'related_customer_id');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function relatedMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'related_merchant_id');
    }

    /**
     * @return HasMany<CustomerExtraRequestTransaction, $this>
     */
    public function customerExtraRequestTransactions(): HasMany
    {
        return $this->hasMany(CustomerExtraRequestTransaction::class);
    }

    /**
     * @return HasMany<MerchantOfferCreditTransaction, $this>
     */
    public function merchantOfferCreditTransactions(): HasMany
    {
        return $this->hasMany(MerchantOfferCreditTransaction::class);
    }

    /**
     * @return HasOne<MarketerCommission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(MarketerCommission::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if ($payment->public_id) {
                return;
            }

            $payment->public_id = (string) Str::ulid();
        });

        static::updating(function (): void {
            throw new LogicException('Paid payment transactions are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Payment transactions cannot be deleted.');
        });
    }
}
