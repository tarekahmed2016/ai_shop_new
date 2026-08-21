<?php

namespace Database\Factories;

use App\Models\CustomerRequest;
use App\Models\RequestImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestImage>
 */
class RequestImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_request_id' => CustomerRequest::factory(),
            'path' => 'customer-requests/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ];
    }
}
