<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'site_code' => 'ST-'.$this->faker->unique()->numberBetween(100, 999999),
            'site_name' => $this->faker->streetName().' Site',
            'location' => $this->faker->address(),
            'status' => 'active',
        ];
    }

    /**
     * NOTE: organization_id must be set explicitly by the caller to match
     * the parent project's organization_id — Site's creating hook enforces
     * this and will abort(403) otherwise. Tests should always do:
     *   Site::factory()->create(['project_id' => $project->id, 'organization_id' => $project->organization_id])
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
        ]);
    }
}
