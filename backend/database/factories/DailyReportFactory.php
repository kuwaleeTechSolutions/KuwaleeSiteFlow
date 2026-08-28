<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_date' => now()->toDateString(),
            'weather' => 'Sunny',
            'work_activities' => $this->faker->sentence(),
            'work_completed' => $this->faker->sentence(),
            'quantity_completed' => $this->faker->randomFloat(2, 1, 500),
            'unit' => 'meters',
            'manpower_deployed' => $this->faker->numberBetween(5, 50),
            'status' => 'draft',
        ];
    }

    /**
     * organization_id/project_id/site_id/created_by must be supplied
     * explicitly by the caller to keep the Site's own organization
     * consistency check satisfied (see DailyReport::booted()).
     */
    public function forSite(Site $site, int $createdBy): static
    {
        return $this->state(fn () => [
            'organization_id' => $site->organization_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'created_by' => $createdBy,
        ]);
    }
}
