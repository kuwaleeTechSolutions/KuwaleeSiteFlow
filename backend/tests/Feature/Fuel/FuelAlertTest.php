<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelTransaction;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class FuelAlertTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_missing_meter_reading_is_flagged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $equipment = $this->createEquipment($organization);

        // Issue with NO opening/closing reading recorded.
        FuelTransaction::create([
            'organization_id' => $organization->id,
            'equipment_id' => $equipment->id,
            'transaction_type' => 'issue',
            'quantity' => 20,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'recorded_by' => $owner->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/fuel-alerts?date=".now()->toDateString()
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data.missing_meter_reading'));
    }

    public function test_high_consumption_spike_is_flagged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $equipment = $this->createEquipment($organization);

        for ($i = 1; $i <= 10; $i++) {
            FuelTransaction::create([
                'organization_id' => $organization->id, 'equipment_id' => $equipment->id,
                'transaction_type' => 'issue', 'quantity' => 15, 'project_id' => $project->id,
                'site_id' => $site->id, 'recorded_by' => $owner->id, 'created_at' => now()->subDays($i),
            ]);
        }

        // Today: a huge spike.
        FuelTransaction::create([
            'organization_id' => $organization->id, 'equipment_id' => $equipment->id,
            'transaction_type' => 'issue', 'quantity' => 150, 'project_id' => $project->id,
            'site_id' => $site->id, 'recorded_by' => $owner->id, 'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/fuel-alerts?date=".now()->toDateString()
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data.high_consumption'));
    }

    public function test_above_configured_threshold_is_flagged_when_organization_sets_one(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $organization->update(['settings' => array_merge($organization->settings ?? [], ['fuel_max_daily_quantity' => 50])]);
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $equipment = $this->createEquipment($organization);

        FuelTransaction::create([
            'organization_id' => $organization->id, 'equipment_id' => $equipment->id,
            'transaction_type' => 'issue', 'quantity' => 75, 'project_id' => $project->id,
            'site_id' => $site->id, 'recorded_by' => $owner->id, 'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/fuel-alerts?date=".now()->toDateString()
        );

        $response->assertOk();
        $alerts = $response->json('data.above_configured_threshold');
        $this->assertCount(1, $alerts);
        $this->assertEquals('75.00', $alerts[0]['quantity_issued']);
    }

    public function test_no_threshold_alert_when_organization_has_not_configured_one(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $equipment = $this->createEquipment($organization);

        FuelTransaction::create([
            'organization_id' => $organization->id, 'equipment_id' => $equipment->id,
            'transaction_type' => 'issue', 'quantity' => 10000, 'project_id' => $project->id,
            'site_id' => $site->id, 'recorded_by' => $owner->id, 'created_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/projects/{$project->uuid}/fuel-alerts?date=".now()->toDateString()
        );

        $response->assertOk();
        $this->assertCount(0, $response->json('data.above_configured_threshold'));
    }
}
