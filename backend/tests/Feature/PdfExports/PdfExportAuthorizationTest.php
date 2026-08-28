<?php

namespace Tests\Feature\PdfExports;

use App\Models\Bill;
use App\Models\Measurement;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class PdfExportAuthorizationTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_unassigned_accounts_manager_cannot_export_project_bill(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accounts = $this->createUserWithRole($organization, 'accounts_manager');
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $bill = Bill::factory()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($accounts)->get("/api/bills/{$bill->uuid}/pdf")->assertStatus(403);
    }

    public function test_cross_organization_measurement_export_is_denied(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $ownerB = $this->createUserWithRole($orgB, 'owner');
        $projectB = $this->createProject($orgB);
        $siteB = $this->createSite($projectB);
        $measurement = Measurement::factory()->create([
            'organization_id' => $orgB->id,
            'project_id' => $projectB->id,
            'site_id' => $siteB->id,
            'created_by' => $ownerB->id,
        ]);

        $this->actingAs($ownerA)->get("/api/measurements/{$measurement->uuid}/pdf")->assertStatus(403);
    }
}
