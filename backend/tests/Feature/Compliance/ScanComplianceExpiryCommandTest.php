<?php

namespace Tests\Feature\Compliance;

use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class ScanComplianceExpiryCommandTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_artisan_command_runs_the_scan_and_reports_notification_count(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(5)->toDateString(),
            'responsible_person_id' => $owner->id,
        ]);

        $this->artisan('compliance:scan-expiry')
            ->expectsOutputToContain('1 notification(s) sent')
            ->assertExitCode(0);
    }
}
