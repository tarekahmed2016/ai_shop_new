<?php

namespace App\Models;

use Database\Factories\CustomerOfferContactRevealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOfferContactReveal extends Model
{
    /** @use HasFactory<CustomerOfferContactRevealFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_request_id',
        'merchant_offer_id',
        'merchant_id',
        'customer_id',
        'revealed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_request_id' => 'integer',
            'merchant_offer_id' => 'integer',
            'merchant_id' => 'integer',
            'customer_id' => 'integer',
            'revealed_at' => 'datetime',
        ];
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

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @param  Builder<CustomerOfferContactReveal>  $query
     * @return Builder<CustomerOfferContactReveal>
     */
    public function scopeForRequest(Builder $query, CustomerRequest|int $request): Builder
    {
        $id = $request instanceof CustomerRequest ? $request->id : $request;

        return $query->where('customer_request_id', $id);
    }

    /**
     * @param  Builder<CustomerOfferContactReveal>  $query
     * @return Builder<CustomerOfferContactReveal>
     */
    public function scopeForMerchant(Builder $query, Merchant|int $merchant): Builder
    {
        $id = $merchant instanceof Merchant ? $merchant->id : $merchant;

        return $query->where('merchant_id', $id);
    }

    /**
     * @param  Builder<CustomerOfferContactReveal>  $query
     * @return Builder<CustomerOfferContactReveal>
     */
    public function scopeForCustomer(Builder $query, Customer|int $customer): Builder
    {
        $id = $customer instanceof Customer ? $customer->id : $customer;

        return $query->where('customer_id', $id);
    }
}
