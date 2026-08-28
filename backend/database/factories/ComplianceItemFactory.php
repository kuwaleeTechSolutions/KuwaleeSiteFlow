<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplianceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => 'General Insurance Policy',
            'type' => 'insurance',
            'issue_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addDays(90)->toDateString(),
            'status' => 'valid',
        ];
    }
}
