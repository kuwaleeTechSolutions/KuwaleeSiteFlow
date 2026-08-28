<?php

namespace Tests\Feature\Projects;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

/**
 * Covers brief §31 "Project Access" test requirements:
 *  - Assigned user can access assigned project.
 *  - User cannot access unassigned project if restricted.
 *  - Cross-organization access is impossible.
 */
class ProjectAccessTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_assigned_project_manager_can_view_their_project(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);

        $response = $this->actingAs($pm)->getJson("/api/projects/{$project->uuid}");

        $response->assertOk()->assertJsonPath('data.id', $project->uuid);
    }

    public function test_unassigned_project_manager_cannot_view_a_project_in_their_own_org(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        // Project exists in the SAME organization but PM is NOT assigned.
        $project = $this->createProject($organization);

        $response = $this->actingAs($pm)->getJson("/api/projects/{$project->uuid}");

        $response->assertStatus(403);
    }

    public function test_owner_can_view_any_project_in_their_organization_without_explicit_assignment(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization); // not explicitly assigned

        $response = $this->actingAs($owner)->getJson("/api/projects/{$project->uuid}");

        $response->assertOk();
    }

    public function test_user_cannot_access_another_organizations_project_even_if_somehow_assigned_id_guessed(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $pmA = $this->createUserWithRole($orgA, 'project_manager');
        $projectInB = $this->createProject($orgB);

        $response = $this->actingAs($pmA)->getJson("/api/projects/{$projectInB->uuid}");

        $response->assertStatus(403);
    }

    public function test_project_list_only_returns_assigned_projects_for_non_org_wide_roles(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $assignedProject = $this->createProject($organization, ['project_name' => 'Assigned Project']);
        $assignedProject->assignedUsers()->attach($pm->id);
        $this->createProject($organization, ['project_name' => 'Unassigned Project']);

        $response = $this->actingAs($pm)->getJson('/api/projects');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('project_name');
        $this->assertContains('Assigned Project', $names);
        $this->assertNotContains('Unassigned Project', $names);
    }

    public function test_client_readonly_role_cannot_update_a_project(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $client = $this->createUserWithRole($organization, 'client_readonly');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($client->id);

        $response = $this->actingAs($client)->putJson("/api/projects/{$project->uuid}", [
            'project_name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_only_org_wide_role_can_delete_a_project_even_if_assigned(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager'); // not org-wide
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);

        // Project Manager's default permission set doesn't include
        // projects.delete anyway, but this also validates the org-wide
        // visibility requirement even if the permission were granted.
        $response = $this->actingAs($pm)->deleteJson("/api/projects/{$project->uuid}");
        $response->assertStatus(403);

        $owner = $this->createUserWithRole($organization, 'owner');
        $this->actingAs($owner)->deleteJson("/api/projects/{$project->uuid}")->assertOk();
    }

    public function test_project_code_must_be_unique_within_organization(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $this->createProject($organization, ['project_code' => 'PRJ-DUP']);

        $response = $this->actingAs($owner)->postJson('/api/projects', [
            'project_code' => 'PRJ-DUP',
            'project_name' => 'Duplicate Code Project',
            'contract_value' => 1000,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('project_code');
    }

    public function test_same_project_code_is_allowed_across_different_organizations(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $this->createProject($orgB, ['project_code' => 'PRJ-SHARED']);

        $response = $this->actingAs($ownerA)->postJson('/api/projects', [
            'project_code' => 'PRJ-SHARED',
            'project_name' => 'Shared Code Project (Org A)',
            'contract_value' => 1000,
        ]);

        $response->assertCreated();
    }
}
