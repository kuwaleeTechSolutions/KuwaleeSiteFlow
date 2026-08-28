<?php

namespace Tests\Feature\Payments;

use App\Models\Bill;
use App\Models\MeasurementItem;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use SetsUpOrganizations;

    private function setupCertifiedBill(float $quantity = 200, float $rate = 250): array
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project, ['contract_quantity' => 1000, 'contract_rate' => $rate]);

        $mCreate = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => $quantity]],
        ])->assertCreated();
        $mUuid = $mCreate->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$mUuid}/submit")->assertOk();
        $this->actingAs($owner)->postJson("/api/measurements/{$mUuid}/approve")->assertOk();

        $measurementItem = MeasurementItem::where('boq_item_id', $boqItem->id)->firstOrFail();

        $bCreate = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => $quantity]],
        ])->assertCreated();
        $bUuid = $bCreate->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/bills/{$bUuid}/submit")->assertOk();
        $this->actingAs($owner)->postJson("/api/bills/{$bUuid}/certify")->assertOk();

        $bill = Bill::where('uuid', $bUuid)->firstOrFail();

        return [$bill, $accountsManager, $owner];
    }

    public function test_can_record_a_partial_payment_against_a_certified_bill(): void
    {
        [$bill, $accountsManager] = $this->setupCertifiedBill(quantity: 200, rate: 250); // net_payable = 50000.00

        $response = $this->actingAs($accountsManager)->postJson("/api/bills/{$bill->uuid}/payments", [
            'payment_date' => now()->toDateString(),
            'amount' => 20000,
            'payment_mode' => 'Bank Transfer',
        ]);

        $response->assertCreated();
        $this->assertEquals('partially_paid', $bill->fresh()->status);
    }

    public function test_full_payment_marks_bill_as_paid(): void
    {
        [$bill, $accountsManager] = $this->setupCertifiedBill(quantity: 200, rate: 250); // net_payable = 50000.00

        $this->actingAs($accountsManager)->postJson("/api/bills/{$bill->uuid}/payments", [
            'payment_date' => now()->toDateString(), 'amount' => 50000,
        ])->assertCreated();

        $this->assertEquals('paid', $bill->fresh()->status);
    }

    public function test_payment_exceeding_outstanding_amount_is_rejected(): void
    {
        [$bill, $accountsManager] = $this->setupCertifiedBill(quantity: 200, rate: 250); // net_payable = 50000.00

        $this->actingAs($accountsManager)->postJson("/api/bills/{$bill->uuid}/payments", [
            'payment_date' => now()->toDateString(), 'amount' => 40000,
        ])->assertCreated();

        // Only 10000 remains outstanding — attempting to pay 15000 must fail.
        $response = $this->actingAs($accountsManager)->postJson("/api/bills/{$bill->uuid}/payments", [
            'payment_date' => now()->toDateString(), 'amount' => 15000,
        ]);

        $response->assertStatus(422);
        $this->assertEquals('partially_paid', $bill->fresh()->status);
    }

    public function test_cannot_record_payment_against_an_uncertified_bill(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($accountsManager->id);
        $site = $this->createSite($project);
        $boqItem = $this->createBoqItem($project);
        $owner = $this->createUserWithRole($organization, 'owner');

        $mCreate = $this->actingAs($accountsManager)->postJson('/api/measurements', [
            'site_id' => $site->id, 'measurement_date' => now()->toDateString(),
            'items' => [['boq_item_id' => $boqItem->id, 'current_quantity' => 50]],
        ])->assertCreated();
        $mUuid = $mCreate->json('data.id');
        $this->actingAs($accountsManager)->postJson("/api/measurements/{$mUuid}/submit")->assertOk();
        $this->actingAs($owner)->postJson("/api/measurements/{$mUuid}/approve")->assertOk();
        $measurementItem = MeasurementItem::where('boq_item_id', $boqItem->id)->firstOrFail();

        $bCreate = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/bills", [
            'bill_number' => 'RA-001', 'bill_type' => 'running', 'bill_date' => now()->toDateString(),
            'billing_period_start' => now()->toDateString(), 'billing_period_end' => now()->toDateString(),
            'items' => [['measurement_item_id' => $measurementItem->id, 'quantity_billed' => 50]],
        ])->assertCreated();
        // Deliberately NOT submitted/certified — still 'draft'.

        $response = $this->actingAs($accountsManager)->postJson("/api/bills/{$bCreate->json('data.id')}/payments", [
            'payment_date' => now()->toDateString(), 'amount' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function test_outstanding_and_paid_amounts_are_always_computed_live(): void
    {
        [$bill, $accountsManager] = $this->setupCertifiedBill(quantity: 200, rate: 250); // net_payable = 50000.00

        $this->actingAs($accountsManager)->postJson("/api/bills/{$bill->uuid}/payments", [
            'payment_date' => now()->toDateString(), 'amount' => 30000,
        ])->assertCreated();

        $response = $this->actingAs($accountsManager)->getJson("/api/bills/{$bill->uuid}");

        $response->assertOk();
        $this->assertEquals('30000.00', $response->json('data.paid_amount'));
        $this->assertEquals('20000.00', $response->json('data.outstanding_amount'));
    }
}
