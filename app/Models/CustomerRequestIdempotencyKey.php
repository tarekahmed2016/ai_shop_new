<?php

namespace App\Models;

use App\Enums\CustomerRequests\IntakeAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequestIdempotencyKey extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'customer_request_id' => 'integer',
            'action' => IntakeAction::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerRequest, $this>
     */
    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}
