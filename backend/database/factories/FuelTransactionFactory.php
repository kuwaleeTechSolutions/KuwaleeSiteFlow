<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FuelTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_type' => 'issue',
            'quantity' => 20,
        ];
    }
}
