<?php

namespace Tests\Feature\Sites;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class SiteAccessTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_site_supervisor_assigned_directly_to_a_site_can_view_it(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);

        $response = $this->actingAs($supervisor)->getJson("/api/sites/{$site->uuid}");

        $response->assertOk()->assertJsonPath('data.id', $site->uuid);
    }

    public function test_site_supervisor_not_assigned_to_the_site_or_project_cannot_view_it(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project); // no assignment at all

        $response = $this->actingAs($supervisor)->getJson("/api/sites/{$site->uuid}");

        $response->assertStatus(403);
    }

    public function test_project_manager_assigned_to_project_can_view_any_of_its_sites_without_direct_site_assignment(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project); // not directly assigned to the site

        $response = $this->actingAs($pm)->getJson("/api/sites/{$site->uuid}");

        $response->assertOk();
    }

    public function test_site_supervisor_assigned_to_one_site_cannot_view_a_sibling_site(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $siteA = $this->createSite($project);
        $siteB = $this->createSite($project);
        $siteA->assignedUsers()->attach($supervisor->id);

        $this->actingAs($supervisor)->getJson("/api/sites/{$siteA->uuid}")->assertOk();
        $this->actingAs($supervisor)->getJson("/api/sites/{$siteB->uuid}")->assertStatus(403);
    }

    public function test_cannot_access_a_site_belonging_to_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $projectB = $this->createProject($orgB);
        $siteInB = $this->createSite($projectB);

        $response = $this->actingAs($ownerA)->getJson("/api/sites/{$siteInB->uuid}");

        $response->assertStatus(403);
    }

    public function test_project_manager_can_create_a_site_under_their_assigned_project(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);

        $response = $this->actingAs($pm)->postJson("/api/projects/{$project->uuid}/sites", [
            'site_code' => 'ST-100',
            'site_name' => 'Drilling Site A',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sites', [
            'project_id' => $project->id,
            'organization_id' => $organization->id,
            'site_code' => 'ST-100',
        ]);
    }

    public function test_project_manager_cannot_create_a_site_under_an_unassigned_project(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization); // not assigned

        $response = $this->actingAs($pm)->postJson("/api/projects/{$project->uuid}/sites", [
            'site_code' => 'ST-200',
            'site_name' => 'Unauthorized Site',
        ]);

        $response->assertStatus(403);
    }

    public function test_site_code_must_be_unique_within_a_project_but_can_repeat_across_projects(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $projectA = $this->createProject($organization);
        $projectB = $this->createProject($organization);
        $this->createSite($projectA, ['site_code' => 'ST-SAME']);

        $this->actingAs($owner)->postJson("/api/projects/{$projectA->uuid}/sites", [
            'site_code' => 'ST-SAME',
            'site_name' => 'Duplicate in Same Project',
        ])->assertStatus(422);

        $this->actingAs($owner)->postJson("/api/projects/{$projectB->uuid}/sites", [
            'site_code' => 'ST-SAME',
            'site_name' => 'Same Code, Different Project',
        ])->assertCreated();
    }

    public function test_only_org_wide_role_can_delete_a_site(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project);

        // project_manager's default permissions do not include sites.delete.
        $this->actingAs($pm)->deleteJson("/api/sites/{$site->uuid}")->assertStatus(403);

        $owner = $this->createUserWithRole($organization, 'owner');
        $this->actingAs($owner)->deleteJson("/api/sites/{$site->uuid}")->assertOk();
    }
}
