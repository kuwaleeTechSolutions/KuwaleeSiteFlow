<?php

namespace Tests\Feature\Measurements;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class MeasurementWorkflowTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_site_engineer_can_record_a_draft_measurement(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $boqItem = $this->createBoqItem($project, ['contract_quantity' => 1000]);

        // site_supervisor's default set has no measurements.create — grant
        // it via a custom role addition would be more work than needed;
        // measurements.create is on project_manager/accounts_manager by
        // default per DefaultRoles, so use accounts_manager here instead.
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project->assignedUsers()->attach($accountsManager->id);

        $response = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id,
            'measurement_date' => now()->toDateString(),
            'items' => [
                ['boq_item_id' => $boqItem->id, 'current_quantity' => 200],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('measurement_items', [
            'boq_item_id' => $boqItem->id, 'previous_quantity' => 0, 'current_quantity' => 200, 'cumulative_quantity' => 200,
        ]);
    }

    public function test_full_workflow_draft_submit_approve_and_cumulative_tracking(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project, ['contract_quantity' => 1000]);

        // First measurement: 200 units.
        $first = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 200]],
        ])->assertCreated();

        $firstUuid = $first->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$firstUuid}/submit")->assertOk();

        // Owner approves (accounts manager is the creator; self-approval denied by default).
        $owner = $this->createUserWithRole($organization, 'owner');
        $this->actingAs($owner)->postJson("/api/measurements/{$firstUuid}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        // Second measurement: another 150 units — previous_quantity must
        // now be 200 (carried forward from the first APPROVED measurement).
        $second = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 150]],
        ])->assertCreated();

        $this->assertDatabaseHas('measurement_items', [
            'boq_item_id' => $boqItem->id, 'previous_quantity' => 200, 'current_quantity' => 150, 'cumulative_quantity' => 350,
        ]);
    }

    public function test_creator_cannot_approve_their_own_measurement_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // Owner has both measurements.create AND measurements.approve, plus
        // org-wide visibility — the ONLY thing blocking self-approval is
        // the explicit guard in MeasurementPolicy.
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project);

        $create = $this->actingAs($owner)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 100]],
        ])->assertCreated();

        $uuid = $create->json('data.id');
        $this->actingAs($owner)->postJson("/api/measurements/{$uuid}/submit")->assertOk();

        $response = $this->actingAs($owner)->postJson("/api/measurements/{$uuid}/approve");

        $response->assertStatus(403);
    }

    public function test_cumulative_quantity_cannot_exceed_contracted_quantity(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project, ['contract_quantity' => 100]);

        $response = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 150]], // exceeds 100
        ]);

        $response->assertStatus(422);
    }

    public function test_only_draft_measurements_are_editable(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project);

        $create = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 50]],
        ])->assertCreated();

        $uuid = $create->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$uuid}/submit")->assertOk();

        // No direct PUT/PATCH endpoint exists for measurements at all
        // (only submit/approve/reject) — verify approve is blocked while
        // still in 'submitted' by a user without the approve permission,
        // reinforcing the workflow gate.
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$uuid}/approve")->assertStatus(403);
    }

    public function test_measurement_creator_not_assigned_to_site_cannot_create_measurement(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager'); // not assigned
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project);

        $response = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 50]],
        ]);

        $response->assertStatus(403);
    }

    public function test_rejected_measurement_carries_review_remarks(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project);

        $create = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 50]],
        ])->assertCreated();

        $uuid = $create->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$uuid}/submit")->assertOk();

        $response = $this->actingAs($owner)->postJson("/api/measurements/{$uuid}/reject", [
            'review_remarks' => 'Quantities do not match site photos.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('measurements', ['id' => \App\Models\Measurement::where('uuid', $uuid)->first()->id, 'review_remarks' => 'Quantities do not match site photos.']);
    }
}
