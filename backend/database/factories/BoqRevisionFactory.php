<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BoqRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'revision_number' => 1,
            'reason' => 'Original BOQ',
            'effective_date' => now()->toDateString(),
        ];
    }
}
