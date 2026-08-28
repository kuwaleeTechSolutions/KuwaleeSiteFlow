<?php

namespace Tests\Feature\Billing;

use App\Models\MeasurementItem;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use SetsUpOrganizations;

    /**
     * Helper: creates a project, BOQ item, and an APPROVED measurement of
     * the given quantity, returning [project, measurementItem, accountsManager, owner].
     */
    private function setupApprovedMeasurement(float $quantity = 200, float $contractQuantity = 1000): array
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project, ['contract_quantity' => $contractQuantity, 'contract_rate' => 250]);

        $create = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => $quantity]],
        ])->assertCreated();

        $uuid = $create->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$uuid}/submit")->assertOk();
        $this->actingAs($owner)->postJson("/api/measurements/{$uuid}/approve")->assertOk();

        $measurementItem = MeasurementItem::where('boq_item_id', $boqItem->id)->firstOrFail();

        return [$project, $measurementItem, $accountsManager, $owner];
    }

    public function test_can_create_a_bill_from_an_approved_measurement(): void
    {
        [$project, $measurementItem, $accountsManager] = $this->setupApprovedMeasurement(quantity: 200);

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001',
            'bill_type' => 'running',
            'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->subDays(30)->toDateString(),
            'billing_period_end' => now()->toDateString(),
            'deductions' => 500,
            'taxes' => 200,
            'items' => [
                ['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 200],
            ],
        ]);

        $response->assertCreated();
        // 200 * 250 = 50000 work value; net = 50000 - 500 - 200 = 49300.00
        $this->assertEquals('50000.00', $response->json('data.current_work_value'));
        $this->assertEquals('49300.00', $response->json('data.net_payable'));
    }

    public function test_cannot_bill_a_quantity_that_was_never_measured(): void
    {
        [$project, $measurementItem, $accountsManager] = $this->setupApprovedMeasurement(quantity: 200);

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-002', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [
                // Only 200 was measured — attempting to bill 500 must fail.
                ['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 500],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_double_bill_the_same_measured_quantity(): void
    {
        [$project, $measurementItem, $accountsManager] = $this->setupApprovedMeasurement(quantity: 200);

        $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 200]],
        ])->assertCreated();

        // The full 200 has already been billed — any further billing
        // against the SAME measurement item must be rejected.
        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-002', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 50]],
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_bill_against_an_unapproved_measurement(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project);

        $create = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 100]],
        ])->assertCreated(); // still DRAFT — never approved

        $measurementItem = MeasurementItem::where('boq_item_id', $boqItem->id)->firstOrFail();

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 50]],
        ]);

        $response->assertStatus(422);
    }

    public function test_bill_number_must_be_unique_within_project(): void
    {
        [$project, $measurementItem, $accountsManager] = $this->setupApprovedMeasurement(quantity: 400);

        $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-DUP', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 100]],
        ])->assertCreated();

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-DUP', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 100]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('bill_number');
    }

    public function test_bill_must_be_submitted_before_it_can_be_certified(): void
    {
        [$project, $measurementItem, $accountsManager] = $this->setupApprovedMeasurement(quantity: 100);

        $create = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 100]],
        ])->assertCreated();

        $uuid = $create->json('data.id');

        // Still 'draft' — certify must be rejected until submitted.
        $this->actingAs($accountsManager)->postJson("/api/bills/{$uuid}/certify")->assertStatus(403);
    }

    public function test_creator_cannot_certify_their_own_bill_by_default(): void
    {
        [$project, $measurementItem, $accountsManager] = $this->setupApprovedMeasurement(quantity: 100);

        $create = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 100]],
        ])->assertCreated();

        $uuid = $create->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/bills/{$uuid}/submit")->assertOk();

        $response = $this->actingAs($accountsManager)->postJson("/api/bills/{$uuid}/certify");

        $response->assertStatus(403);
    }

    public function test_owner_can_certify_a_bill_created_by_someone_else(): void
    {
        [$project, $measurementItem, $accountsManager, $owner] = $this->setupApprovedMeasurement(quantity: 100);

        $create = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 100]],
        ])->assertCreated();

        $uuid = $create->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/bills/{$uuid}/submit")->assertOk();

        $response = $this->actingAs($owner)->postJson("/api/bills/{$uuid}/certify");

        $response->assertOk()->assertJsonPath('data.status', 'certified');
    }
}
