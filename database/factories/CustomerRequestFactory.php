<?php

namespace Database\Factories;

use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status;
use App\Models\Customer;
use App\Models\CustomerRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerRequest>
 */
class CustomerRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'customer_id' => Customer::factory(),
            'request_text' => fake()->sentence(12),
            'status' => Status::New,
            'source' => Source::Admin,
            'category_id' => null,
            'normalized_request_json' => null,
        ];
    }
}
