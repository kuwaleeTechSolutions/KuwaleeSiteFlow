<?php

namespace Tests\Feature\Labour;

use App\Models\WorkerAttendance;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class WageComputationTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_wage_computation_produces_exact_decimal_totals(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $worker = $this->createWorker($organization, ['daily_wage' => 800.00]);

        // 5 full days present + 4 hours overtime total, within the period.
        for ($i = 0; $i < 5; $i++) {
            WorkerAttendance::factory()->create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'site_id' => $site->id,
                'worker_id' => $worker->id,
                'attendance_date' => now()->subDays($i)->toDateString(),
                'shift' => 'day',
                'status' => 'present',
                'overtime_hours' => $i === 0 ? 4 : 0,
                'marked_by' => $owner->id,
            ]);
        }

        $response = $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/wage-computations/generate", [
            'period_start' => now()->subDays(6)->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $data = $response->json('data.0');

        // base = 800 * 5 days = 4000.00
        // hourly = 800 / 8 = 100.0000; overtime rate = 100 * 1.5 = 150.0000
        // overtime_total = 150 * 4 hours = 600.00
        // gross = 4000.00 + 600.00 = 4600.00
        $this->assertEquals('5.00', $data['days_present']);
        $this->assertEquals('4.00', $data['overtime_hours']);
        $this->assertEquals('4000.00', $data['base_wage_total']);
        $this->assertEquals('600.00', $data['overtime_total']);
        $this->assertEquals('4600.00', $data['gross_total']);
    }

    public function test_half_day_counts_as_half_in_days_present(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $worker = $this->createWorker($organization, ['daily_wage' => 1000.00]);

        WorkerAttendance::factory()->create([
            'organization_id' => $organization->id, 'project_id' => $project->id, 'site_id' => $site->id,
            'worker_id' => $worker->id, 'attendance_date' => now()->toDateString(), 'shift' => 'day',
            'status' => 'half_day', 'marked_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/wage-computations/generate", [
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $data = $response->json('data.0');

        $this->assertEquals('0.50', $data['days_present']);
        $this->assertEquals('500.00', $data['base_wage_total']); // 1000 * 0.5
    }

    public function test_hr_labour_manager_without_labour_wages_permission_cannot_generate_wages(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $hr = $this->createUserWithRole($organization, 'hr_labour_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($hr->id);

        $response = $this->actingAs($hr)->postJson("/api/projects/{$project->uuid}/wage-computations/generate", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_accounts_manager_with_wages_permission_but_unassigned_project_cannot_generate(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $project = $this->createProject($organization); // not assigned

        $response = $this->actingAs($accountsManager)->postJson("/api/projects/{$project->uuid}/wage-computations/generate", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_wage_computation_regeneration_preserves_prior_history(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $worker = $this->createWorker($organization, ['daily_wage' => 500.00]);

        WorkerAttendance::factory()->create([
            'organization_id' => $organization->id, 'project_id' => $project->id, 'site_id' => $site->id,
            'worker_id' => $worker->id, 'attendance_date' => now()->toDateString(), 'shift' => 'day',
            'status' => 'present', 'marked_by' => $owner->id,
        ]);

        $period = ['period_start' => now()->toDateString(), 'period_end' => now()->toDateString()];

        $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/wage-computations/generate", $period)->assertCreated();
        $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/wage-computations/generate", $period)->assertCreated();

        // Two separate historical rows must exist — regeneration does not
        // overwrite/delete the prior computation.
        $this->assertDatabaseCount('wage_computations', 2);
    }

    public function test_cross_tenant_project_id_cannot_be_used_to_generate_wages(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $projectInB = $this->createProject($orgB);

        $response = $this->actingAs($ownerA)->postJson("/api/projects/{$projectInB->uuid}/wage-computations/generate", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }
}
