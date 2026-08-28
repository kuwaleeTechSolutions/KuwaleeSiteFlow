<?php

namespace Tests\Feature\Materials;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class MaterialTransactionTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_store_manager_can_record_an_inward_transaction(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id,
            'transaction_type' => 'inward',
            'quantity' => 500,
            'site_id' => $site->id,
            'reference_number' => 'PO-1001',
        ]);

        $response->assertCreated()->assertJsonPath('data.transaction_type', 'inward');
        $this->assertDatabaseHas('material_stocks', [
            'material_id' => $material->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 500,
        ]);
    }

    public function test_issuing_material_decreases_stock_correctly(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 500, 'site_id' => $site->id,
        ])->assertCreated();

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'issue', 'quantity' => 150, 'site_id' => $site->id,
        ])->assertCreated();

        $this->assertDatabaseHas('material_stocks', [
            'material_id' => $material->id, 'site_id' => $site->id, 'quantity_on_hand' => 350,
        ]);
    }

    public function test_issue_exceeding_available_stock_is_rejected_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 100, 'site_id' => $site->id,
        ])->assertCreated();

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'issue', 'quantity' => 150, 'site_id' => $site->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('material_stocks', [
            'material_id' => $material->id, 'site_id' => $site->id, 'quantity_on_hand' => 100,
        ]);
    }

    public function test_store_manager_without_override_permission_cannot_force_negative_stock(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager'); // no override permission by default
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'issue', 'quantity' => 50,
            'site_id' => $site->id, 'force_override' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_with_override_permission_can_force_negative_stock(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner'); // has negative_stock_override
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $response = $this->actingAs($owner)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'issue', 'quantity' => 50,
            'site_id' => $site->id, 'force_override' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.is_override', true);
        $this->assertDatabaseHas('material_stocks', [
            'material_id' => $material->id, 'site_id' => $site->id, 'quantity_on_hand' => -50,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'material_transaction.created']);
    }

    public function test_transfer_moves_stock_atomically_between_sites(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $siteA = $this->createSite($project);
        $siteB = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 300, 'site_id' => $siteA->id,
        ])->assertCreated();

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'transfer', 'quantity' => 120,
            'site_id' => $siteA->id, 'to_site_id' => $siteB->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('material_stocks', ['material_id' => $material->id, 'site_id' => $siteA->id, 'quantity_on_hand' => 180]);
        $this->assertDatabaseHas('material_stocks', ['material_id' => $material->id, 'site_id' => $siteB->id, 'quantity_on_hand' => 120]);
    }

    public function test_transfer_exceeding_source_stock_is_rejected_and_destination_unchanged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $siteA = $this->createSite($project);
        $siteB = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 50, 'site_id' => $siteA->id,
        ])->assertCreated();

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'transfer', 'quantity' => 200,
            'site_id' => $siteA->id, 'to_site_id' => $siteB->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('material_stocks', ['material_id' => $material->id, 'site_id' => $siteB->id]);
    }

    public function test_adjustment_requires_explicit_direction(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'adjustment', 'quantity' => 10, 'site_id' => $site->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('direction');
    }

    public function test_adjustment_decrease_reduces_stock(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 100, 'site_id' => $site->id,
        ])->assertCreated();

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'adjustment', 'quantity' => 15,
            'direction' => 'decrease', 'site_id' => $site->id, 'remarks' => 'Wastage correction',
        ])->assertCreated();

        $this->assertDatabaseHas('material_stocks', [
            'material_id' => $material->id, 'site_id' => $site->id, 'quantity_on_hand' => 85,
        ]);
    }

    public function test_site_supervisor_without_materials_issue_permission_cannot_issue(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // site_supervisor's default set has materials.view/create but NOT materials.issue.
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $material = $this->createMaterial($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'issue', 'quantity' => 10, 'site_id' => $site->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_manager_not_assigned_to_project_cannot_record_transaction(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization); // not assigned
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $response = $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 100, 'site_id' => $site->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_use_a_material_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $storeManagerA = $this->createUserWithRole($orgA, 'store_manager');
        $projectA = $this->createProject($orgA);
        $projectA->assignedUsers()->attach($storeManagerA->id);
        $siteA = $this->createSite($projectA);
        $materialInB = $this->createMaterial($orgB);

        $response = $this->actingAs($storeManagerA)->postJson('/api/material-transactions', [
            'material_id' => $materialInB->id, 'transaction_type' => 'inward', 'quantity' => 100, 'site_id' => $siteA->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_ledger_rows_are_never_deleted_or_updated_by_subsequent_corrections(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($storeManager->id);
        $site = $this->createSite($project);
        $material = $this->createMaterial($organization);

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'inward', 'quantity' => 100, 'site_id' => $site->id,
        ])->assertCreated();

        $this->actingAs($storeManager)->postJson('/api/material-transactions', [
            'material_id' => $material->id, 'transaction_type' => 'adjustment', 'quantity' => 10,
            'direction' => 'decrease', 'site_id' => $site->id,
        ])->assertCreated();

        // Both the original inward AND the correcting adjustment must exist
        // as separate, untouched ledger rows.
        $this->assertDatabaseCount('material_transactions', 2);
        $this->assertDatabaseHas('material_transactions', ['transaction_type' => 'inward', 'quantity' => 100]);
        $this->assertDatabaseHas('material_transactions', ['transaction_type' => 'adjustment', 'quantity' => 10]);
    }
}
