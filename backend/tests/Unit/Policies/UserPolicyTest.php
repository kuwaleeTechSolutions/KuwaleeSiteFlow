<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use SetsUpOrganizations;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
    }

    public function test_view_allows_same_organization_with_permission(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $other = User::factory()->create(['organization_id' => $organization->id]);

        $this->assertTrue($this->policy->view($owner, $other));
    }

    public function test_view_denies_cross_organization_even_with_permission(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $userInB = User::factory()->create(['organization_id' => $orgB->id]);

        $this->assertFalse($this->policy->view($ownerA, $userInB));
    }

    public function test_view_allows_self_even_without_users_view_permission(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // site_supervisor does not have users.view by default
        $siteSupervisor = $this->createUserWithRole($organization, 'site_supervisor');

        $this->assertTrue($this->policy->view($siteSupervisor, $siteSupervisor));
    }

    public function test_delete_denies_self_deletion(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $this->assertFalse($this->policy->delete($owner, $owner));
    }

    public function test_assign_role_denies_self_elevation(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $this->assertFalse($this->policy->assignRole($owner, $owner));
    }

    public function test_assign_role_denies_cross_organization_target(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $userInB = User::factory()->create(['organization_id' => $orgB->id]);

        $this->assertFalse($this->policy->assignRole($ownerA, $userInB));
    }
}
