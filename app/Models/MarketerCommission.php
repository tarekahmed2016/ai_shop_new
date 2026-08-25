<?php

namespace App\Models;

use App\Enums\MarketerCommissions\CommissionType;
use App\Enums\MarketerCommissions\Status;
use App\Enums\Payments\Type as PaymentType;
use Database\Factories\MarketerCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'public_id',
    'marketer_id',
    'marketer_referral_id',
    'payment_transaction_id',
    'referred_user_id',
    'payment_type',
    'payment_amount',
    'commission_type',
    'commission_rate',
    'commission_amount',
    'status',
    'earned_at',
    'notes',
])]
class MarketerCommission extends Model
{
    /** @use HasFactory<MarketerCommissionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['status_formatted', 'payment_type_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'commission_type' => CommissionType::class,
            'status' => Status::class,
            'payment_amount' => 'decimal:3',
            'commission_rate' => 'decimal:3',
            'commission_amount' => 'decimal:3',
            'earned_at' => 'datetime',
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
     * @return Attribute<array{value: int, label: string, name: string}|null, never>
     */
    protected function paymentTypeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_type === null ? null : [
                'value' => $this->payment_type->value,
                'label' => $this->payment_type->label(),
                'name' => $this->payment_type->name,
            ]
        );
    }

    /**
     * @return BelongsTo<Marketer, $this>
     */
    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    /**
     * @return BelongsTo<MarketerReferral, $this>
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(MarketerReferral::class, 'marketer_referral_id');
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
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $commission): void {
            if ($commission->public_id) {
                return;
            }

            $commission->public_id = (string) Str::ulid();
        });
    }
}
