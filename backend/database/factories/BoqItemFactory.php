<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BoqItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = 1000;
        $rate = 250.00;

        return [
            'item_number' => '1.'.$this->faker->unique()->numberBetween(1, 999),
            'description' => 'Excavation in ordinary soil',
            'unit' => 'cum',
            'contract_quantity' => $quantity,
            'contract_rate' => $rate,
            'contract_value' => bcmul((string) $quantity, (string) $rate, 2),
        ];
    }
}
