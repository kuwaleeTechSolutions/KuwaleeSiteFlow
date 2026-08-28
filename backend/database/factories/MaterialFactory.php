<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'material_code' => 'MAT-'.$this->faker->unique()->numberBetween(1000, 999999),
            'material_name' => $this->faker->randomElement(['OPC 53 Cement', 'TMT Steel Bar 12mm', 'Coarse Aggregate', 'River Sand', 'Diesel']),
            'category' => $this->faker->randomElement(['Cement', 'Steel', 'Aggregate', 'Fuel']),
            'unit' => $this->faker->randomElement(['bags', 'kg', 'cum', 'litres']),
            'minimum_stock' => 100,
            'status' => 'active',
        ];
    }
}
