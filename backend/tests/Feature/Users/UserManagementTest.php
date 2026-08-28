<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_owner_can_create_a_user_with_a_role(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $siteSupervisorRole = Role::where('organization_id', $organization->id)
            ->where('slug', 'site_supervisor')->firstOrFail();

        $response = $this->actingAs($owner)->postJson('/api/users', [
            'name' => 'Field Engineer',
            'email' => 'field.engineer@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role_ids' => [$siteSupervisorRole->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'field.engineer@example.com');
        $this->assertDatabaseHas('users', [
            'organization_id' => $organization->id,
            'email' => 'field.engineer@example.com',
        ]);
    }

    public function test_duplicate_email_within_same_organization_is_rejected(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $existing = User::factory()->create(['organization_id' => $organization->id]);
        $ownerRole = Role::where('organization_id', $organization->id)->where('slug', 'owner')->firstOrFail();

        $response = $this->actingAs($owner)->postJson('/api/users', [
            'name' => 'Duplicate',
            'email' => $existing->email,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role_ids' => [$ownerRole->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_same_email_is_allowed_across_different_organizations(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $ownerRoleB = Role::where('organization_id', $orgB->id)->where('slug', 'owner')->firstOrFail();

        User::factory()->create(['organization_id' => $orgB->id, 'email' => 'shared@example.com']);

        $response = $this->actingAs($ownerA)->postJson('/api/users', [
            'name' => 'Shared Email User (Org A)',
            'email' => 'shared@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role_ids' => [Role::where('organization_id', $orgA->id)->where('slug', 'owner')->firstOrFail()->id],
        ]);

        $response->assertCreated();
    }

    public function test_user_creation_writes_an_audit_log_entry(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $ownerRole = Role::where('organization_id', $organization->id)->where('slug', 'owner')->firstOrFail();

        $this->actingAs($owner)->postJson('/api/users', [
            'name' => 'Audited User',
            'email' => 'audited.user@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role_ids' => [$ownerRole->id],
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'action' => 'user.created',
        ]);

        $log = AuditLog::where('action', 'user.created')->latest('id')->first();
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }

    public function test_role_change_on_a_user_is_audited(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $target = User::factory()->create(['organization_id' => $organization->id]);
        $newRole = Role::where('organization_id', $organization->id)->where('slug', 'store_manager')->firstOrFail();

        $this->actingAs($owner)->postJson("/api/users/{$target->uuid}/roles", [
            'role_ids' => [$newRole->id],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.role_changed',
            'entity_id' => $target->id,
        ]);
    }
}
