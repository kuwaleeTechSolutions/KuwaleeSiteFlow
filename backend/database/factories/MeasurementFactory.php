<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MeasurementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measurement_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }
}
