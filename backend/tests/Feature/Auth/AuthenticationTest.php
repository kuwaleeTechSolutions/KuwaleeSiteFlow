<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_disabled_user_cannot_login(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $user = User::factory()->disabled()->create([
            'organization_id' => $organization->id,
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        // The 6th attempt, even with correct credentials, must be throttled.
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'correct-password']);
        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_unauthenticated_user_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/users')->assertStatus(401);
    }

    public function test_authenticated_user_can_logout_and_session_is_invalidated(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $user = $this->createUserWithRole($organization, 'owner');

        $this->actingAs($user);
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_me_endpoint_returns_permissions_and_organization(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $user = $this->createUserWithRole($organization, 'site_supervisor');

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.is_super_admin', false)
            ->assertJsonPath('data.organization.name', $organization->name)
            ->assertJsonFragment(['daily_reports.create']);
    }
}
