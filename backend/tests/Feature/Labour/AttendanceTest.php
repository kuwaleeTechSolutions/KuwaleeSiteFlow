<?php

namespace Tests\Feature\Labour;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_site_supervisor_can_mark_attendance_for_their_assigned_site(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $worker = $this->createWorker($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/attendance', [
            'site_id' => $site->id,
            'worker_id' => $worker->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('worker_attendance', [
            'worker_id' => $worker->id,
            'site_id' => $site->id,
            'status' => 'present',
        ]);
    }

    public function test_site_supervisor_cannot_mark_attendance_for_an_unassigned_site(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project); // not assigned
        $worker = $this->createWorker($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/attendance', [
            'site_id' => $site->id,
            'worker_id' => $worker->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_attendance_for_same_worker_date_shift_is_rejected(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $worker = $this->createWorker($organization);

        $payload = [
            'site_id' => $site->id,
            'worker_id' => $worker->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ];

        $this->actingAs($supervisor)->postJson('/api/attendance', $payload)->assertCreated();
        $response = $this->actingAs($supervisor)->postJson('/api/attendance', $payload);

        $response->assertStatus(422);
    }

    public function test_bulk_attendance_creates_multiple_records_atomically(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $workerA = $this->createWorker($organization);
        $workerB = $this->createWorker($organization);

        $response = $this->actingAs($supervisor)->postJson('/api/attendance/bulk', [
            'site_id' => $site->id,
            'attendance_date' => now()->toDateString(),
            'shift' => 'day',
            'entries' => [
                ['worker_id' => $workerA->id, 'status' => 'present'],
                ['worker_id' => $workerB->id, 'status' => 'half_day'],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('worker_attendance', 2);
    }

    public function test_bulk_attendance_is_all_or_nothing_when_one_entry_is_a_duplicate(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $workerA = $this->createWorker($organization);
        $workerB = $this->createWorker($organization);

        // Pre-existing record for workerA on this date/shift.
        $this->actingAs($supervisor)->postJson('/api/attendance', [
            'site_id' => $site->id,
            'worker_id' => $workerA->id,
            'attendance_date' => now()->toDateString(),
            'shift' => 'day',
            'status' => 'present',
        ])->assertCreated();

        $response = $this->actingAs($supervisor)->postJson('/api/attendance/bulk', [
            'site_id' => $site->id,
            'attendance_date' => now()->toDateString(),
            'shift' => 'day',
            'entries' => [
                ['worker_id' => $workerA->id, 'status' => 'present'], // duplicate
                ['worker_id' => $workerB->id, 'status' => 'present'], // would otherwise succeed
            ],
        ]);

        $response->assertStatus(422);
        // workerB's entry must NOT have been committed either (atomicity).
        $this->assertDatabaseMissing('worker_attendance', ['worker_id' => $workerB->id]);
    }

    public function test_cannot_mark_attendance_for_a_worker_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $supervisorA = $this->createUserWithRole($orgA, 'site_supervisor');
        $projectA = $this->createProject($orgA);
        $siteA = $this->createSite($projectA);
        $siteA->assignedUsers()->attach($supervisorA->id);
        $workerInB = $this->createWorker($orgB);

        $response = $this->actingAs($supervisorA)->postJson('/api/attendance', [
            'site_id' => $siteA->id,
            'worker_id' => $workerInB->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $response->assertStatus(422);
    }
}
