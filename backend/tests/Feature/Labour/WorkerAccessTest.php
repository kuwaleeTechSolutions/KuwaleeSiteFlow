<?php

namespace Tests\Feature\Labour;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class WorkerAccessTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_hr_labour_manager_can_view_workers_but_daily_wage_is_hidden(): void
    {
        $organization = $this->createOrganizationWithRoles();
        // hr_labour_manager holds labour.view but NOT labour.wages by default.
        $hr = $this->createUserWithRole($organization, 'hr_labour_manager');
        $worker = $this->createWorker($organization, ['daily_wage' => 750.00]);

        $response = $this->actingAs($hr)->getJson("/api/workers/{$worker->uuid}");

        $response->assertOk();
        $response->assertJsonMissingPath('data.daily_wage');
    }

    public function test_owner_with_labour_wages_permission_can_see_daily_wage(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $worker = $this->createWorker($organization, ['daily_wage' => 750.00]);

        $response = $this->actingAs($owner)->getJson("/api/workers/{$worker->uuid}");

        $response->assertOk()->assertJsonPath('data.daily_wage', '750.00');
    }

    public function test_accounts_manager_does_not_have_labour_view_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $accountsManager = $this->createUserWithRole($organization, 'accounts_manager');
        $worker = $this->createWorker($organization);

        $this->actingAs($accountsManager)->getJson("/api/workers/{$worker->uuid}")->assertStatus(403);
    }

    public function test_worker_code_must_be_unique_within_organization(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $this->createWorker($organization, ['worker_code' => 'WK-DUP']);

        $response = $this->actingAs($owner)->postJson('/api/workers', [
            'worker_code' => 'WK-DUP',
            'name' => 'Duplicate Worker',
            'daily_wage' => 600,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('worker_code');
    }

    public function test_cannot_view_a_worker_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $workerInB = $this->createWorker($orgB);

        $this->actingAs($ownerA)->getJson("/api/workers/{$workerInB->uuid}")->assertStatus(403);
    }

    public function test_updating_daily_wage_is_audited_even_though_hidden_from_some_viewers(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $worker = $this->createWorker($organization, ['daily_wage' => 600]);

        $this->actingAs($owner)->putJson("/api/workers/{$worker->uuid}", [
            'daily_wage' => 700,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'worker.updated',
            'entity_id' => $worker->id,
        ]);
    }
}
