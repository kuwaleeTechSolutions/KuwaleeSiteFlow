<?php

namespace Tests\Feature\Equipment;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class EquipmentAccessTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_owner_can_create_equipment(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $response = $this->actingAs($owner)->postJson('/api/equipment', [
            'equipment_code' => 'EQ-001',
            'equipment_name' => 'Excavator 01',
            'type' => 'Excavator',
        ]);

        $response->assertCreated();
    }

    public function test_project_manager_cannot_create_new_equipment_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // project_manager holds equipment.view/update/log_usage but NOT
        // equipment.create — registering new fleet assets stays Owner/Admin.
        $pm = $this->createUserWithRole($organization, 'project_manager');

        $response = $this->actingAs($pm)->postJson('/api/equipment', [
            'equipment_code' => 'EQ-002',
            'equipment_name' => 'Crane 01',
        ]);

        $response->assertStatus(403);
    }

    public function test_project_manager_assigned_to_equipments_project_can_update_its_status(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $equipment = $this->createEquipment($organization, ['assigned_project_id' => $project->id]);

        $response = $this->actingAs($pm)->putJson("/api/equipment/{$equipment->uuid}", [
            'status' => 'maintenance',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'maintenance');
    }

    public function test_project_manager_not_assigned_to_equipments_project_cannot_update_it(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization); // not assigned
        $equipment = $this->createEquipment($organization, ['assigned_project_id' => $project->id]);

        $response = $this->actingAs($pm)->putJson("/api/equipment/{$equipment->uuid}", [
            'status' => 'breakdown',
        ]);

        $response->assertStatus(403);
    }

    public function test_only_org_wide_role_can_delete_equipment(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $admin = $this->createUserWithRole($organization, 'admin');
        $equipment = $this->createEquipment($organization);

        $this->actingAs($admin)->deleteJson("/api/equipment/{$equipment->uuid}")->assertOk();
    }

    public function test_cannot_view_equipment_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $equipmentInB = $this->createEquipment($orgB);

        $this->actingAs($ownerA)->getJson("/api/equipment/{$equipmentInB->uuid}")->assertStatus(403);
    }
}
