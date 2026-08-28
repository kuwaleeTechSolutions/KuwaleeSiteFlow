<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'worker_code' => 'WK-'.$this->faker->unique()->numberBetween(1000, 999999),
            'name' => $this->faker->name(),
            'phone' => $this->faker->numerify('##########'),
            'trade' => $this->faker->randomElement(['Mason', 'Carpenter', 'Electrician', 'Helper', 'Welder']),
            'skill_category' => $this->faker->randomElement(['Skilled', 'Semi-skilled', 'Unskilled']),
            'daily_wage' => $this->faker->randomElement([500, 600, 750, 900]),
            'joining_date' => now()->subMonths(3),
            'status' => 'active',
        ];
    }
}
