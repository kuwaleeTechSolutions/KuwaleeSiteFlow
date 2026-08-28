<?php

namespace Tests\Feature\Fuel;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class FuelTransactionTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_site_supervisor_can_issue_fuel_to_equipment_with_meter_readings(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $equipment = $this->createEquipment($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'opening_reading' => 1000,
            'closing_reading' => 1050,
            'quantity' => 20,
        ]);

        $response->assertCreated();
        $data = $response->json('data');
        // 20 litres / 50 units delta = 0.4000 consumption rate
        $this->assertEquals('0.4000', $data['consumption_rate']);
    }

    public function test_issue_requires_equipment_id_but_purchase_does_not(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // Owner holds fuel.create (unlike store_manager, which has none of
        // the fuel.* permissions by default) so authorization passes and
        // we reach the actual validation rule being tested here.
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);

        // 'issue' without equipment_id must fail validation.
        $this->actingAs($owner)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'site_id' => $site->id,
            'quantity' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors('equipment_id');

        // 'purchase' without equipment_id (bulk depot stock-in) succeeds.
        $this->actingAs($owner)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'purchase',
            'site_id' => $site->id,
            'quantity' => 500,
            'unit_cost' => 95.50,
        ])->assertCreated();
    }

    public function test_total_cost_is_computed_server_side_via_bcmath(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);

        $response = $this->actingAs($owner)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'purchase',
            'site_id' => $site->id,
            'quantity' => 300,
            'unit_cost' => 96.75,
        ]);

        $response->assertCreated();
        // 300 * 96.75 = 29025.00 — client never supplies total_cost.
        $this->assertEquals('29025.00', $response->json('data.total_cost'));
    }

    public function test_closing_reading_must_be_greater_than_opening_reading(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $equipment = $this->createEquipment($organization);

        $response = $this->actingAs($owner)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'opening_reading' => 1000,
            'closing_reading' => 900, // invalid: less than opening
            'quantity' => 10,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('closing_reading');
    }

    public function test_site_supervisor_not_assigned_to_site_cannot_record_fuel_transaction(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project); // not assigned
        $equipment = $this->createEquipment($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'opening_reading' => 100,
            'closing_reading' => 120,
            'quantity' => 8,
        ]);

        $response->assertStatus(403);
    }

    public function test_reviewed_transaction_becomes_immutable(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $owner = $this->createUserWithRole($organization, 'owner'); // owner holds fuel.approve
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $equipment = $this->createEquipment($organization);

        $create = $this->actingAs($supervisor)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'opening_reading' => 500,
            'closing_reading' => 540,
            'quantity' => 15,
        ])->assertCreated();

        $uuid = $create->json('data.id');

        $this->actingAs($owner)->postJson("/api/fuel-transactions/{$uuid}/review")->assertOk();

        $response = $this->actingAs($supervisor)->putJson("/api/fuel-transactions/{$uuid}", [
            'quantity' => 999,
        ]);

        $response->assertStatus(403);
    }

    public function test_site_supervisor_cannot_review_their_own_fuel_entry_without_fuel_approve_permission(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // site_supervisor's default set has fuel.view/create but not fuel.approve.
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $equipment = $this->createEquipment($organization);

        $create = $this->actingAs($supervisor)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'equipment_id' => $equipment->id,
            'site_id' => $site->id,
            'opening_reading' => 200,
            'closing_reading' => 220,
            'quantity' => 8,
        ])->assertCreated();

        $response = $this->actingAs($supervisor)->postJson("/api/fuel-transactions/{$create->json('data.id')}/review");

        $response->assertStatus(403);
    }

    public function test_cannot_use_equipment_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $projectA = $this->createProject($orgA);
        $siteA = $this->createSite($projectA);
        $equipmentInB = $this->createEquipment($orgB);

        $response = $this->actingAs($ownerA)->postJson('/api/fuel-transactions', [
            'transaction_type' => 'issue',
            'equipment_id' => $equipmentInB->id,
            'site_id' => $siteA->id,
            'opening_reading' => 100,
            'closing_reading' => 120,
            'quantity' => 8,
        ]);

        $response->assertStatus(422);
    }
}
