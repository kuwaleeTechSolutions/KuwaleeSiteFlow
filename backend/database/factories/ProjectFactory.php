<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'project_code' => 'PRJ-'.$this->faker->unique()->numberBetween(1000, 999999),
            'project_name' => $this->faker->catchPhrase().' Project',
            'client_name' => $this->faker->company(),
            'contract_number' => 'CN-'.$this->faker->numerify('######'),
            'contract_value' => $this->faker->randomFloat(2, 100000, 50000000),
            'start_date' => now()->subMonths(2),
            'expected_end_date' => now()->addMonths(6),
            'status' => 'active',
            'description' => $this->faker->sentence(),
        ];
    }
}
