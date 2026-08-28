<?php

namespace Tests\Feature\Equipment;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class EquipmentUsageLogTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_site_supervisor_can_log_equipment_usage_for_their_assigned_site(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $equipment = $this->createEquipment($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/equipment-usage-logs', [
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'usage_date' => now()->toDateString(),
            'hours_used' => 6.5,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('equipment_usage_logs', [
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
        ]);
    }

    public function test_site_supervisor_not_assigned_to_site_cannot_log_usage(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project); // not assigned
        $equipment = $this->createEquipment($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/equipment-usage-logs', [
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'usage_date' => now()->toDateString(),
            'hours_used' => 4,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_manager_lacks_equipment_log_usage_permission_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // store_manager's default set has no equipment.* permissions at all.
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $equipment = $this->createEquipment($organization);

        $response = $this->actingAs($storeManager)->postJson('/api/equipment-usage-logs', [
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'usage_date' => now()->toDateString(),
            'hours_used' => 4,
        ]);

        $response->assertStatus(403);
    }
}
