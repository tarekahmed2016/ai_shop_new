<?php

namespace App\Models;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use Database\Factories\MerchantOfferCreditTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'merchant_id',
    'type',
    'source',
    'amount',
    'paid_amount',
    'payment_transaction_id',
    'balance_after',
    'reference',
    'notes',
    'created_by_user_id',
    'customer_request_id',
    'merchant_offer_id',
])]
class MerchantOfferCreditTransaction extends Model
{
    /** @use HasFactory<MerchantOfferCreditTransactionFactory> */
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
            'paid_amount' => 'decimal:3',
            'payment_transaction_id' => 'integer',
            'balance_after' => 'integer',
            'merchant_id' => 'integer',
            'created_by_user_id' => 'integer',
            'customer_request_id' => 'integer',
            'merchant_offer_id' => 'integer',
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
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    /**
     * @return BelongsTo<MerchantOffer, $this>
     */
    public function merchantOffer(): BelongsTo
    {
        return $this->belongsTo(MerchantOffer::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            if ($transaction->balance_after !== null) {
                return;
            }

            $previous = (int) static::query()
                ->where('merchant_id', $transaction->merchant_id)
                ->sum('amount');

            $transaction->balance_after = $previous + (int) $transaction->amount;
        });
    }
}
