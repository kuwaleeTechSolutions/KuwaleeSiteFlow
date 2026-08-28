<?php

namespace Tests\Feature\Dashboards;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_owner_receives_organization_dashboard(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $this->createProject($organization);

        $response = $this->actingAs($owner)->getJson('/api/dashboard');

        $response->assertOk()->assertJsonPath('data.dashboard_type', 'organization');
    }

    public function test_project_manager_only_receives_assigned_projects(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $assigned = $this->createProject($organization, ['project_name' => 'Assigned']);
        $this->createProject($organization, ['project_name' => 'Hidden']);
        $assigned->assignedUsers()->attach($pm->id);

        $response = $this->actingAs($pm)->getJson('/api/dashboard');

        $response->assertOk()->assertJsonPath('data.dashboard_type', 'assigned_projects');
        $names = collect($response->json('data.projects'))->pluck('project.project_name');
        $this->assertContains('Assigned', $names);
        $this->assertNotContains('Hidden', $names);
    }

    public function test_site_supervisor_only_receives_assigned_sites(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $assigned = $this->createSite($project, ['site_name' => 'Assigned Site']);
        $this->createSite($project, ['site_name' => 'Hidden Site']);
        $assigned->assignedUsers()->attach($supervisor->id);

        $response = $this->actingAs($supervisor)->getJson('/api/dashboard');

        $response->assertOk()->assertJsonPath('data.dashboard_type', 'assigned_sites');
        $names = collect($response->json('data.sites'))->pluck('site_name');
        $this->assertContains('Assigned Site', $names);
        $this->assertNotContains('Hidden Site', $names);
    }

    public function test_unassigned_user_cannot_open_project_dashboard(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);

        $this->actingAs($pm)->getJson("/api/projects/{$project->uuid}/dashboard")->assertStatus(403);
    }
}
