<?php

namespace Database\Factories;

use App\Enums\RequestClassifications\Status;
use App\Models\CustomerRequest;
use App\Models\RequestClassification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestClassification>
 */
class RequestClassificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'customer_request_id' => CustomerRequest::factory(),
            'provider' => 'fake',
            'model' => 'fake-v1',
            'detected_item' => null,
            'suggested_category_id' => null,
            'confidence' => null,
            'alternatives' => null,
            'needs_more_information' => false,
            'question' => null,
            'reason' => null,
            'status' => Status::Suggested,
            'customer_confirmed_category_id' => null,
            'confirmed_at' => null,
            'input_has_image' => false,
        ];
    }
}
