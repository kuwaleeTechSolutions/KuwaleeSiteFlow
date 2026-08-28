<?php

namespace Tests\Unit\Services;

use App\Models\Bill;
use App\Models\Measurement;
use App\Services\PdfExportService;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class PdfExportServiceTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_bill_export_returns_pdf_bytes(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $bill = Bill::factory()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_by' => $owner->id,
        ]);

        $bytes = app(PdfExportService::class)->bill($bill);

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_measurement_export_returns_pdf_bytes(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $measurement = Measurement::factory()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'created_by' => $owner->id,
        ]);

        $bytes = app(PdfExportService::class)->measurement($measurement);

        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
