<?php

namespace Tests\Feature\DailyReports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class DailyReportPhotoTest extends TestCase
{
    use SetsUpOrganizations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private-documents');
    }

    public function test_supervisor_can_upload_a_valid_photo_to_their_own_draft_report(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $report = $this->createDailyReport($site, $supervisor);

        $file = UploadedFile::fake()->image('site-photo.jpg', 800, 600);

        $response = $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/photos", [
            'photo' => $file,
            'caption' => 'Excavation progress',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('daily_report_photos', [
            'daily_report_id' => $report->id,
            'caption' => 'Excavation progress',
        ]);

        // The stored path must NEVER be the original filename, and must
        // live on the private disk.
        $photo = \App\Models\DailyReportPhoto::first();
        $this->assertNotEquals('site-photo.jpg', basename($photo->disk_path));
        Storage::disk('private-documents')->assertExists($photo->disk_path);
    }

    public function test_upload_rejects_disguised_executable_file(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $report = $this->createDailyReport($site, $supervisor);

        // A .jpg-named file whose actual content is a PHP script — the
        // 'mimetypes' validation rule sniffs real content, not the
        // extension, so this must be rejected.
        $maliciousFile = UploadedFile::fake()->createWithContent(
            'innocuous.jpg',
            "<?php system(\$_GET['cmd']); ?>"
        );

        $response = $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/photos", [
            'photo' => $maliciousFile,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('daily_report_photos', 0);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $report = $this->createDailyReport($site, $supervisor);

        $oversized = UploadedFile::fake()->image('huge.jpg')->size(config('daily_reports.max_photo_size_kb') + 1024);

        $response = $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/photos", [
            'photo' => $oversized,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_upload_photo_to_an_approved_report(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $pm = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($pm->id);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $report = $this->createDailyReport($site, $supervisor);

        $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/submit")->assertOk();
        $this->actingAs($pm)->postJson("/api/daily-reports/{$report->uuid}/approve")->assertOk();

        $file = UploadedFile::fake()->image('late-photo.jpg');
        $response = $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/photos", [
            'photo' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_download_a_photo_from_a_site_they_are_not_assigned_to(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisorA = $this->createUserWithRole($organization, 'site_supervisor');
        $supervisorB = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $siteA = $this->createSite($project);
        $siteB = $this->createSite($project);
        $siteA->assignedUsers()->attach($supervisorA->id);
        $siteB->assignedUsers()->attach($supervisorB->id);

        $reportA = $this->createDailyReport($siteA, $supervisorA);
        $file = UploadedFile::fake()->image('a.jpg');
        $this->actingAs($supervisorA)->postJson("/api/daily-reports/{$reportA->uuid}/photos", ['photo' => $file])
            ->assertCreated();

        $photo = \App\Models\DailyReportPhoto::first();

        $response = $this->actingAs($supervisorB)->getJson("/api/daily-report-photos/{$photo->uuid}/download");

        $response->assertStatus(403);
    }

    public function test_photo_response_never_exposes_raw_disk_path(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $site = $this->createSite($project);
        $site->assignedUsers()->attach($supervisor->id);
        $report = $this->createDailyReport($site, $supervisor);

        $file = UploadedFile::fake()->image('photo.jpg');
        $response = $this->actingAs($supervisor)->postJson("/api/daily-reports/{$report->uuid}/photos", [
            'photo' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonMissingPath('data.disk_path');
        $this->assertArrayHasKey('download_url', $response->json('data'));
    }
}
