<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->jobTitle(),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'is_system' => false,
            'org_wide_visibility' => false,
        ];
    }

    public function template(): static
    {
        return $this->state(fn () => ['organization_id' => null, 'is_system' => true]);
    }

    public function orgWide(): static
    {
        return $this->state(fn () => ['org_wide_visibility' => true]);
    }
}
