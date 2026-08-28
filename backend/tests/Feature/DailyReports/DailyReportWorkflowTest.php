<?php

namespace Tests\Feature\DailyReports;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class DailyReportWorkflowTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_site_supervisor_can_create_a_draft_report_for_their_assigned_site(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);

        $response = $this->actingAs($supervisor)->postJson('/api/daily-reports', [
            'site_id' => $site->id,
            'report_date' => now()->toDateString(),
            'work_activities' => 'Excavation work',
            'manpower_deployed' => 12,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('daily_reports', [
            'site_id' => $site->id,
            'project_id' => $project->id,
            'status' => 'draft',
        ]);
    }

    public function test_site_supervisor_not_assigned_to_the_site_cannot_create_a_report(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project); // not assigned

        $response = $this->actingAs($supervisor)->postJson('/api/daily-reports', [
            'site_id' => $site->id,
            'report_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_full_workflow_draft_submit_approve(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);

        $report = $this->createDailyReport($site, $supervisor);

        $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->actingAs($pm)->postJson("/api/daily-reports/{$report->uuid}/approve", [
            'review_remarks' => 'Looks good.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('audit_logs', ['action' => 'daily_report.submitted', 'entity_id' => $report->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'daily_report.approved', 'entity_id' => $report->id]);
    }

    public function test_full_workflow_draft_submit_return_then_resubmit(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);

        $report = $this->createDailyReport($site, $supervisor);
        $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/submit")->assertOk();

        $this->actingAs($pm)->postJson("/api/daily-reports/{$report->uuid}/return", [
            'review_remarks' => 'Please add manpower count.',
        ])->assertOk()->assertJsonPath('data.status', 'returned');

        // Returned reports are editable again by the original submitter.
        $this->actingAs($supervisor)->putJson("/api/daily-reports/{$report->uuid}", [
            'manpower_deployed' => 20,
        ])->assertOk();

        $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    public function test_cannot_approve_own_submitted_report_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // Owner has both daily_reports.create/update AND .approve permissions,
        // and org-wide visibility, so the ONLY thing blocking self-approval
        // here is the explicit self-approval guard in the policy.
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);

        $report = $this->createDailyReport($site, $owner);
        $this->actingAs($owner)->postJson("/api/daily-reports/{$report->uuid}/submit")->assertOk();

        $response = $this->actingAs($owner)->postJson("/api/daily-reports/{$report->uuid}/approve");

        $response->assertStatus(403);
    }

    public function test_self_approval_allowed_when_organization_setting_enables_it(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $organization->update(['settings' => array_merge($organization->settings ?? [], ['allow_self_approval' => true])]);
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);

        $report = $this->createDailyReport($site, $owner->fresh());
        $this->actingAs($owner)->postJson("/api/daily-reports/{$report->uuid}/submit")->assertOk();

        $response = $this->actingAs($owner)->postJson("/api/daily-reports/{$report->uuid}/approve");

        $response->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_cannot_approve_a_report_that_is_still_in_draft(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);

        $report = $this->createDailyReport($site, $supervisor); // still draft

        $response = $this->actingAs($pm)->postJson("/api/daily-reports/{$report->uuid}/approve");

        $response->assertStatus(403);
    }

    public function test_approved_report_cannot_be_edited(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);

        $report = $this->createDailyReport($site, $supervisor);
        $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/submit")->assertOk();
        $this->actingAs($pm)->postJson("/api/daily-reports/{$report->uuid}/approve")->assertOk();

        $response = $this->actingAs($supervisor)->putJson("/api/daily-reports/{$report->uuid}", [
            'manpower_deployed' => 999,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_edit_another_users_draft_report_without_org_wide_visibility(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisorA = $this->createUserWithRole($organization, 'site_supervisor');
        $supervisorB = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach([$supervisorA->id, $supervisorB->id]);

        $report = $this->createDailyReport($site, $supervisorA);

        $response = $this->actingAs($supervisorB)->putJson("/api/daily-reports/{$report->uuid}", [
            'work_activities' => 'Tampered',
        ]);

        $response->assertStatus(403);
    }

    public function test_report_cannot_be_created_for_a_site_in_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $supervisorA = $this->createUserWithRole($orgA, 'site_supervisor');
        $projectB = $this->createProject($orgB);
        $siteInB = $this->createSite($projectB);

        $response = $this->actingAs($supervisorA)->postJson('/api/daily-reports', [
            'site_id' => $siteInB->id,
            'report_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }
}
