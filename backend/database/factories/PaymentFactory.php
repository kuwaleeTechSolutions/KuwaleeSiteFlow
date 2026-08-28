<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'payment_mode' => 'Bank Transfer',
        ];
    }
}
