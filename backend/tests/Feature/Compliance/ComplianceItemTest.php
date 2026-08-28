<?php

namespace Tests\Feature\Compliance;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class ComplianceItemTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_owner_can_create_a_compliance_item(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $response = $this->actingAs($owner)->postJson('/api/compliance-items', [
            'title' => 'Contractor All Risk Insurance',
            'type' => 'insurance',
            'issue_date' => now()->subMonths(6)->toDateString(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'valid');
    }

    public function test_site_supervisor_without_compliance_permission_cannot_view_compliance_items(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor'); // no compliance.* by default
        $this->createComplianceItem($organization);

        $this->actingAs($supervisor)->getJson('/api/compliance-items')->assertStatus(403);
    }

    public function test_cannot_view_compliance_item_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $itemInB = $this->createComplianceItem($orgB);

        $this->actingAs($ownerA)->getJson("/api/compliance-items/{$itemInB->uuid}")->assertStatus(403);
    }

    public function test_updating_expiry_date_resets_alert_tracking(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(5)->toDateString(),
            'status' => 'expiring',
            'last_alert_threshold_days' => 7,
        ]);

        $response = $this->actingAs($owner)->putJson("/api/compliance-items/{$item->uuid}", [
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'valid');
        $this->assertDatabaseHas('compliance_items', [
            'id' => $item->id, 'status' => 'valid', 'last_alert_threshold_days' => null,
        ]);
    }
}
