<?php

namespace Tests\Feature\Boq;

use App\Models\BoqItem;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class BoqRevisionTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_accounts_manager_can_create_the_first_boq_revision(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/boq-items/revisions", [
            'reason' => 'Initial BOQ upload',
            'effective_date' => now()->toDateString(),
            'items' => [
                ['item_number' => '1.01', 'description' => 'Excavation', 'unit' => 'cum', 'contract_quantity' => 1000, 'contract_rate' => 250],
                ['item_number' => '1.02', 'description' => 'Backfilling', 'unit' => 'cum', 'contract_quantity' => 500, 'contract_rate' => 150],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('boq_revisions', ['project_id' => $project->id, 'revision_number' => 1]);
        $this->assertDatabaseCount('boq_items', 2);
        // contract_value computed server-side: 1000 * 250 = 250000.00
        $this->assertDatabaseHas('boq_items', ['item_number' => '1.01', 'contract_value' => 250000.00]);
    }

    public function test_revising_an_item_creates_a_new_row_without_touching_the_original(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $originalItem = $this->createBoqItem($project, ['item_number' => '1.01', 'contract_quantity' => 1000, 'contract_rate' => 250]);

        $response = $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/boq-items/revisions", [
            'reason' => 'Quantity increased per site instruction',
            'effective_date' => now()->toDateString(),
            'items' => [
                ['item_number' => '1.01', 'description' => 'Excavation (revised)', 'unit' => 'cum', 'contract_quantity' => 1200, 'contract_rate' => 250],
            ],
        ]);

        $response->assertCreated();

        // The ORIGINAL row must still exist, completely untouched.
        $this->assertDatabaseHas('boq_items', [
            'id' => $originalItem->id, 'contract_quantity' => 1000.000,
        ]);
        // A NEW row exists for the same item_number with the new quantity.
        $this->assertDatabaseHas('boq_items', [
            'item_number' => '1.01', 'contract_quantity' => 1200.000,
        ]);
        $this->assertDatabaseCount('boq_items', 2);
    }

    public function test_current_effective_boq_returns_only_the_latest_revision_per_item(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $this->createBoqItem($project, ['item_number' => '1.01', 'contract_quantity' => 1000]);

        $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/boq-items/revisions", [
            'reason' => 'Revision 2', 'effective_date' => now()->toDateString(),
            'items' => [['item_number' => '1.01', 'description' => 'Excavation v2', 'unit' => 'cum', 'contract_quantity' => 1500, 'contract_rate' => 250]],
        ])->assertCreated();

        $response = $this->actingAs($owner)->getJson("/api/projects/{$project->uuid}/boq-items");

        $response->assertOk();
        $items = $response->json('data');
        $this->assertCount(1, $items); // only ONE current row for item_number 1.01
        $this->assertEquals('1500.000', $items[0]['contract_quantity']);
    }

    public function test_boq_item_cannot_be_updated_directly_at_the_model_layer(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $project = $this->createProject($organization);
        $item = $this->createBoqItem($project);

        $this->expectException(\LogicException::class);
        $item->update(['contract_quantity' => 9999]);
    }

    public function test_site_supervisor_cannot_create_a_boq_revision(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor'); // no billing.* permissions
        $project = $this->createProject($organization);

        $response = $this->actingAs($supervisor)->postJson("/api/projects/{$project->uuid}/boq-items/revisions", [
            'reason' => 'x', 'effective_date' => now()->toDateString(),
            'items' => [['item_number' => '1.01', 'description' => 'x', 'unit' => 'cum', 'contract_quantity' => 1, 'contract_rate' => 1]],
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_create_boq_revision_for_unassigned_project(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization); // not assigned

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/boq-items/revisions", [
            'reason' => 'x', 'effective_date' => now()->toDateString(),
            'items' => [['item_number' => '1.01', 'description' => 'x', 'unit' => 'cum', 'contract_quantity' => 1, 'contract_rate' => 1]],
        ]);

        $response->assertStatus(403);
    }
}
