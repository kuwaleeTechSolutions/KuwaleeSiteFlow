<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bill_number' => 'RA-'.$this->faker->unique()->numberBetween(1000, 999999),
            'bill_type' => 'running',
            'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->subDays(30)->toDateString(),
            'billing_period_end' => now()->toDateString(),
            'status' => 'draft',
        ];
    }
}
