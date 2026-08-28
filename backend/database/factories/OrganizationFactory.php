<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $name = $this->faker->unique()->company(),
            'slug' => \Illuminate\Support\Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'legal_name' => $this->faker->company().' Pvt. Ltd.',
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->numerify('##########'),
            'city' => 'Guwahati',
            'state' => 'Assam',
            'country' => 'India',
            'status' => 'active',
            'settings' => [
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'DD-MM-YYYY',
            ],
        ];
    }
}
