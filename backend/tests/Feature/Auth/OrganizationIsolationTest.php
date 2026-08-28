<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

/**
 * Verifies the four-layer organization isolation strategy end-to-end:
 * a user from Organization A must NEVER be able to view, enumerate, or
 * mutate a resource belonging to Organization B, even by guessing IDs.
 */
class OrganizationIsolationTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_user_cannot_view_another_organizations_user_by_guessing_uuid(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();

        $userA = $this->createUserWithRole($orgA, 'owner');
        $userB = User::factory()->create(['organization_id' => $orgB->id]);

        $response = $this->actingAs($userA)->getJson("/api/users/{$userB->uuid}");

        // Must be denied — never a 200 with cross-tenant data, and never a
        // 500 that could leak existence information via a stack trace.
        $response->assertStatus(403);
    }

    public function test_user_list_never_includes_another_organizations_users(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();

        $userA = $this->createUserWithRole($orgA, 'owner');
        $userB = User::factory()->create(['organization_id' => $orgB->id]);

        $response = $this->actingAs($userA)->getJson('/api/users');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertNotContains($userB->email, $emails);
    }

    public function test_user_cannot_assign_a_role_belonging_to_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();

        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $targetInA = User::factory()->create(['organization_id' => $orgA->id]);
        $roleFromB = Role::where('organization_id', $orgB->id)->where('slug', 'site_supervisor')->firstOrFail();

        $response = $this->actingAs($ownerA)->postJson("/api/users/{$targetInA->uuid}/roles", [
            'role_ids' => [$roleFromB->id],
        ]);

        // Rejected at Form Request validation layer (role must belong to
        // the same organization as the authenticated user).
        $response->assertStatus(422);
    }

    public function test_user_cannot_view_another_organizations_role(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();

        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $roleInB = Role::where('organization_id', $orgB->id)->where('slug', 'accounts_manager')->firstOrFail();

        $response = $this->actingAs($ownerA)->getJson("/api/roles/{$roleInB->uuid}");

        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_access_tenant_scoped_routes(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_tenant_user_cannot_access_super_admin_system_routes(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $response = $this->actingAs($owner)->getJson('/api/system/organizations');

        $response->assertStatus(403);
    }

    public function test_suspended_organization_blocks_its_users(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $organization->update(['status' => 'suspended']);
        $owner = $this->createUserWithRole($organization, 'owner');

        $response = $this->actingAs($owner)->getJson('/api/users');

        $response->assertStatus(403);
    }
}
