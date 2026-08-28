<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_code' => 'EQ-'.$this->faker->unique()->numberBetween(1000, 999999),
            'equipment_name' => $this->faker->randomElement(['Excavator', 'JCB Backhoe', 'Mobile Crane', 'Diesel Generator', 'Tipper Truck']),
            'type' => $this->faker->randomElement(['Excavator', 'JCB', 'Crane', 'Generator', 'Truck']),
            'registration_number' => strtoupper($this->faker->bothify('AS-##-??-####')),
            'status' => 'available',
        ];
    }
}
