<?php

namespace Tests\Feature\Projects;

use App\Models\User;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class ProjectAssignmentTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_owner_can_assign_users_to_a_project(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $pm = $this->createUserWithRole($organization, 'project_manager');

        $response = $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/users", [
            'user_ids' => [$pm->id],
        ]);

        $response->assertOk();
        $this->assertTrue($project->fresh()->isUserAssigned($pm->id));
    }

    public function test_project_manager_without_org_wide_visibility_cannot_assign_users(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $anotherUser = User::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($pm)->postJson("/api/projects/{$project->uuid}/users", [
            'user_ids' => [$anotherUser->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_assign_a_user_from_a_different_organization_to_a_project(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $project = $this->createProject($orgA);
        $userInB = User::factory()->create(['organization_id' => $orgB->id]);

        $response = $this->actingAs($ownerA)->postJson("/api/projects/{$project->uuid}/users", [
            'user_ids' => [$userInB->id],
        ]);

        $response->assertStatus(422);
        $this->assertFalse($project->fresh()->isUserAssigned($userInB->id));
    }

    public function test_assignment_change_is_audited(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $pm = $this->createUserWithRole($organization, 'project_manager');

        $this->actingAs($owner)->postJson("/api/projects/{$project->uuid}/users", [
            'user_ids' => [$pm->id],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'project.users_assigned',
            'entity_id' => $project->id,
        ]);
    }
}
