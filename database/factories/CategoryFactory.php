<?php

namespace Database\Factories;

use App\Enums\Categories\Status;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->unique()->words(2, true);

        return [
            'public_id' => (string) Str::ulid(),
            'name_ar' => 'تصنيف '.fake()->unique()->numerify('###'),
            'name_en' => Str::title($nameEn),
            'slug' => Str::slug($nameEn).'-'.fake()->unique()->numerify('###'),
            'parent_id' => null,
            'status' => Status::Active,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Inactive,
        ]);
    }
}
