<?php

namespace Tests\Feature\Rbac;

use App\Models\Role;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_organization_receives_all_eight_default_roles_on_creation(): void
    {
        $organization = $this->createOrganizationWithRoles();

        $slugs = Role::where('organization_id', $organization->id)->pluck('slug')->sort()->values()->all();

        $this->assertEquals([
            'accounts_manager', 'admin', 'client_readonly', 'hr_labour_manager',
            'owner', 'project_manager', 'site_supervisor', 'store_manager',
        ], $slugs);
    }

    public function test_system_role_template_cannot_be_updated_via_api(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $template = Role::whereNull('organization_id')->where('slug', 'owner')->firstOrFail();

        $response = $this->actingAs($owner)->putJson("/api/roles/{$template->uuid}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_organizations_own_cloned_system_role_cannot_be_deleted(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $siteSupervisorRole = Role::where('organization_id', $organization->id)
            ->where('slug', 'site_supervisor')->firstOrFail();

        $response = $this->actingAs($owner)->deleteJson("/api/roles/{$siteSupervisorRole->uuid}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['id' => $siteSupervisorRole->id]);
    }

    public function test_custom_role_can_be_updated_and_deleted_by_authorized_user(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $create = $this->actingAs($owner)->postJson('/api/roles', [
            'name' => 'Site Photographer',
            'slug' => 'site-photographer',
            'permissions' => ['documents.upload'],
        ])->assertCreated();

        $roleId = $create->json('data.id');

        $this->actingAs($owner)->putJson("/api/roles/{$roleId}", [
            'name' => 'Site Photographer II',
        ])->assertOk()->assertJsonPath('data.name', 'Site Photographer II');

        $this->actingAs($owner)->deleteJson("/api/roles/{$roleId}")->assertOk();
        $this->assertSoftDeletedOrMissing('roles', $roleId);
    }

    /**
     * Helper: the roles table has no soft-deletes migration column in
     * Phase 2, so a delete is a hard delete — assert absence instead.
     */
    private function assertSoftDeletedOrMissing(string $table, string $uuid): void
    {
        $this->assertDatabaseMissing($table, ['uuid' => $uuid]);
    }
}
