<?php

namespace App\Models;

use App\Enums\Payments\Method;
use Database\Factories\MarketerPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'public_id',
    'marketer_id',
    'amount',
    'payment_method',
    'reference',
    'notes',
    'paid_at',
    'created_by_user_id',
])]
class MarketerPayout extends Model
{
    /** @use HasFactory<MarketerPayoutFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['payment_method_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'payment_method' => Method::class,
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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
     * @return BelongsTo<Marketer, $this>
     */
    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $payout): void {
            if ($payout->public_id) {
                return;
            }

            $payout->public_id = (string) Str::ulid();
        });

        static::updating(function (): void {
            throw new LogicException('Marketer payouts are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Marketer payouts cannot be deleted.');
        });
    }
}
