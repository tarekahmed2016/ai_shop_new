<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->words(3, true),
            'name_en' => fake()->words(3, true),
            'client_name_ar' => fake()->optional()->company(),
            'client_name_en' => fake()->optional()->company(),
            'description_ar' => fake()->optional()->sentence(),
            'description_en' => fake()->optional()->sentence(),
            'project_date' => fake()->optional()->date(),
            'project_url' => fake()->optional()->url(),
            'ordering' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
