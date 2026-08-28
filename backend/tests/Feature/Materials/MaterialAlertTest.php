<?php

namespace Tests\Feature\Materials;

use App\Models\MaterialTransaction;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class MaterialAlertTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_unusually_high_issue_quantity_is_flagged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        // Establish a trailing history of ~10 units/day issued for 10 days.
        for ($i = 1; $i <= 10; $i++) {
            MaterialTransaction::create([
                'organization_id' => $organization->id,
                'material_id' => $material->id,
                'transaction_type' => 'issue',
                'quantity' => 10,
                'project_id' => $project->id,
                'site_id' => $site->id,
                'created_by' => $owner->id,
                'created_at' => now()->subDays($i),
            ]);
        }

        // Today: a huge spike — 100 units (10x the trailing average, well
        // above the configured 2x default multiplier).
        MaterialTransaction::create([
            'organization_id' => $organization->id,
            'material_id' => $material->id,
            'transaction_type' => 'issue',
            'quantity' => 100,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/material-alerts/high-consumption?date=".now()->toDateString()
        );

        $response->assertOk();
        $alerts = $response->json('data');
        $this->assertCount(1, $alerts);
        $this->assertEquals('100.000', $alerts[0]['quantity_issued']);
    }

    public function test_first_ever_issue_with_no_history_is_not_flagged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        MaterialTransaction::create([
            'organization_id' => $organization->id,
            'material_id' => $material->id,
            'transaction_type' => 'issue',
            'quantity' => 500, // large, but there's no history to compare against
            'project_id' => $project->id,
            'site_id' => $site->id,
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/material-alerts/high-consumption?date=".now()->toDateString()
        );

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_normal_consumption_within_threshold_is_not_flagged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        for ($i = 1; $i <= 10; $i++) {
            MaterialTransaction::create([
                'organization_id' => $organization->id, 'material_id' => $material->id,
                'transaction_type' => 'issue', 'quantity' => 10, 'project_id' => $project->id,
                'site_id' => $site->id, 'created_by' => $owner->id, 'created_at' => now()->subDays($i),
            ]);
        }

        // Today: 12 units — only slightly above average, well under the 2x threshold.
        MaterialTransaction::create([
            'organization_id' => $organization->id, 'material_id' => $material->id,
            'transaction_type' => 'issue', 'quantity' => 12, 'project_id' => $project->id,
            'site_id' => $site->id, 'created_by' => $owner->id, 'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/material-alerts/high-consumption?date=".now()->toDateString()
        );

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
