<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->name(),
            'name_en' => fake()->name(),
            'position_ar' => fake()->optional()->jobTitle(),
            'position_en' => fake()->optional()->jobTitle(),
            'bio_ar' => fake()->optional()->paragraph(),
            'bio_en' => fake()->optional()->paragraph(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'linkedin_url' => fake()->optional()->url(),
            'ordering' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
