<?php

namespace Database\Factories;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerExtraRequests\TransactionType;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerExtraRequestTransaction>
 */
class CustomerExtraRequestTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => TransactionType::ManualAdd,
            'amount' => 5,
            'source' => TransactionSource::ManualAdjustment,
            'payment_transaction_id' => null,
            'reference' => null,
            'notes' => null,
            'created_by_user_id' => null,
            'customer_request_id' => null,
        ];
    }
}
