<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_user_without_permission_cannot_create_a_role(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // Site Supervisor's default permission set does not include roles.create.
        $siteSupervisor = $this->createUserWithRole($organization, 'site_supervisor');

        $response = $this->actingAs($siteSupervisor)->postJson('/api/roles', [
            'name' => 'Custom Role',
            'slug' => 'custom-role',
            'permissions' => ['projects.view'],
        ]);

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_create_a_role(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $response = $this->actingAs($owner)->postJson('/api/roles', [
            'name' => 'Custom Role',
            'slug' => 'custom-role',
            'permissions' => ['projects.view', 'daily_reports.view'],
        ]);

        $response->assertCreated()->assertJsonPath('data.slug', 'custom-role');
        $this->assertDatabaseHas('roles', [
            'organization_id' => $organization->id,
            'slug' => 'custom-role',
        ]);
    }

    public function test_user_without_users_view_permission_cannot_list_users(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // hr_labour_manager's default set has no users.* permissions.
        $hr = $this->createUserWithRole($organization, 'hr_labour_manager');

        $this->actingAs($hr)->getJson('/api/users')->assertStatus(403);
    }

    public function test_user_can_always_view_and_update_own_profile_without_users_permission(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $siteSupervisor = $this->createUserWithRole($organization, 'site_supervisor');

        $this->actingAs($siteSupervisor)
            ->getJson("/api/users/{$siteSupervisor->uuid}")
            ->assertOk();

        $this->actingAs($siteSupervisor)
            ->putJson("/api/users/{$siteSupervisor->uuid}", ['name' => 'Updated Name'])
            ->assertOk();
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $response = $this->actingAs($owner)->deleteJson("/api/users/{$owner->uuid}");

        $response->assertStatus(403);
    }

    public function test_hardcoded_role_string_checks_are_not_used_role_is_permission_driven(): void
    {
        // A user with a CUSTOM role slug (not one of the eight named
        // defaults) but holding the 'roles.view' permission must still be
        // able to list roles — proving authorization is permission-driven,
        // not based on comparing $user->role against a fixed string.
        $organization = $this->createOrganizationWithRoles();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $customRole = \App\Models\Role::create([
            'organization_id' => $organization->id,
            'name' => 'Bespoke Auditor',
            'slug' => 'bespoke-auditor',
            'is_system' => false,
            'org_wide_visibility' => false,
        ]);
        $permission = \App\Models\Permission::where('name', 'roles.view')->firstOrFail();
        $customRole->permissions()->attach($permission->id);
        app(\App\Services\RoleService::class)->assignRole($user, $customRole);

        $this->actingAs($user->fresh(['roles.permissions']))
            ->getJson('/api/roles')
            ->assertOk();
    }

    public function test_accounts_manager_can_access_billing_but_not_user_management(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');

        $this->actingAs($accountsManager)->getJson('/api/users')->assertStatus(403);
        // billing.* endpoints are added in Phase 8; permission presence is
        // verified directly here as a forward-looking contract test.
        $this->assertTrue($accountsManager->hasPermission('billing.approve'));
        $this->assertFalse($accountsManager->hasPermission('users.create'));
    }
}
