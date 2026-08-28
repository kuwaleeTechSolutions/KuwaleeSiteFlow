<?php

namespace Tests\Feature\Security;

use App\Models\Bill;
use App\Models\Document;
use App\Models\Measurement;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class IdorRegressionTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_cross_organisation_resource_matrix_is_denied(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $ownerB = $this->createUserWithRole($orgB, 'owner');
        $projectB = $this->createProject($orgB);
        $siteB = $this->createSite($projectB);

        $document = $this->createDocument($orgB, $ownerB);
        $measurement = Measurement::factory()->create([
            'organization_id' => $orgB->id,
            'project_id' => $projectB->id,
            'site_id' => $siteB->id,
            'created_by' => $ownerB->id,
        ]);
        $bill = Bill::factory()->create([
            'organization_id' => $orgB->id,
            'project_id' => $projectB->id,
            'created_by' => $ownerB->id,
        ]);

        $paths = [
            "/api/projects/{$projectB->uuid}",
            "/api/sites/{$siteB->uuid}",
            "/api/documents/{$document->uuid}",
            "/api/measurements/{$measurement->uuid}",
            "/api/bills/{$bill->uuid}",
        ];

        foreach ($paths as $path) {
            $this->actingAs($ownerA)->getJson($path)->assertStatus(403);
        }
    }

    public function test_client_cannot_mass_assign_organisation_id_on_create(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');

        $response = $this->actingAs($ownerA)->postJson('/api/projects', [
            'organization_id' => $orgB->id,
            'project_code' => 'SEC-001',
            'project_name' => 'Security Test Project',
            'contract_value' => 1000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('projects', [
            'project_code' => 'SEC-001',
            'organization_id' => $orgA->id,
        ]);
        $this->assertDatabaseMissing('projects', [
            'project_code' => 'SEC-001',
            'organization_id' => $orgB->id,
        ]);
    }
}
