<?php

namespace Database\Factories;

use App\Enums\Payments\Method;
use App\Enums\Payments\Status;
use App\Enums\Payments\Type;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'payer_user_id' => User::factory(),
            'type' => Type::CustomerExtraRequests,
            'amount' => '2.000',
            'status' => Status::Paid,
            'payment_method' => Method::BankTransfer,
            'reference' => null,
            'notes' => null,
            'paid_at' => now(),
            'created_by_user_id' => null,
            'related_customer_id' => null,
            'related_merchant_id' => null,
        ];
    }
}
