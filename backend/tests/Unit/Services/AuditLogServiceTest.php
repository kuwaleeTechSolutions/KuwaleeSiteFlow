<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuditLogService;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_sensitive_keys_are_stripped_before_persisting(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $this->actingAs($owner);

        $target = User::factory()->create(['organization_id' => $organization->id]);

        $log = app(AuditLogService::class)->log(
            'user.updated',
            $target,
            $owner,
            ['password' => 'old-hash', 'name' => 'Old Name'],
            ['password' => 'new-hash', 'remember_token' => 'abc', 'api_key' => 'xyz', 'name' => 'New Name'],
        );

        $this->assertArrayNotHasKey('password', $log->old_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
        $this->assertArrayNotHasKey('api_key', $log->new_values);
        $this->assertEquals('New Name', $log->new_values['name']);
        $this->assertEquals('Old Name', $log->old_values['name']);
    }

    public function test_audit_log_cannot_be_updated_after_creation(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $log = app(AuditLogService::class)->log('user.login', $owner, $owner);

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }
}
