<?php

namespace Tests\Unit\Services;

use App\Models\ComplianceItem;
use App\Notifications\ComplianceExpiryNotification;
use App\Services\ComplianceAlertService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class ComplianceAlertServiceTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_item_expiring_within_30_days_is_flagged_and_notified(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(25)->toDateString(),
            'responsible_person_id' => $owner->id,
        ]);

        app(ComplianceAlertService::class)->scan();

        $item->refresh();
        $this->assertEquals('expiring', $item->status);
        $this->assertEquals(30, $item->last_alert_threshold_days);
        Notification::assertSentTo($owner, ComplianceExpiryNotification::class);
    }

    public function test_expired_item_is_flagged_with_zero_threshold(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->subDays(3)->toDateString(),
        ]);

        app(ComplianceAlertService::class)->scan();

        $item->refresh();
        $this->assertEquals('expired', $item->status);
        $this->assertEquals(0, $item->last_alert_threshold_days);
    }

    public function test_item_far_from_expiry_is_not_flagged(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(120)->toDateString(),
        ]);

        app(ComplianceAlertService::class)->scan();

        $item->refresh();
        $this->assertEquals('valid', $item->status);
        $this->assertNull($item->last_alert_threshold_days);
        Notification::assertNothingSent();
    }

    public function test_duplicate_notifications_are_not_sent_for_the_same_threshold_on_repeated_scans(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(25)->toDateString(),
            'responsible_person_id' => $owner->id,
        ]);

        $service = app(ComplianceAlertService::class);
        $firstRunCount = $service->scan();
        $secondRunCount = $service->scan(); // same day, same threshold crossed

        $this->assertEquals(1, $firstRunCount);
        $this->assertEquals(0, $secondRunCount);
        Notification::assertSentToTimes($owner, ComplianceExpiryNotification::class, 1);
    }

    public function test_crossing_a_tighter_threshold_sends_a_new_notification(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(25)->toDateString(), // crosses 30-day threshold
            'responsible_person_id' => $owner->id,
        ]);

        $service = app(ComplianceAlertService::class);
        $service->scan(); // fires the 30-day alert

        // Time passes — now within the 15-day window.
        $item->update(['expiry_date' => now()->addDays(10)->toDateString()]);
        $notifiedCount = $service->scan();

        $this->assertEquals(1, $notifiedCount);
        $this->assertEquals(15, $item->fresh()->last_alert_threshold_days);
        Notification::assertSentToTimes($owner, ComplianceExpiryNotification::class, 2);
    }

    public function test_item_with_no_responsible_person_notifies_org_wide_roles(): void
    {
        Notification::fake();

        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner'); // org_wide_visibility = true
        $projectManager = $this->createUserWithRole($organization, 'project_manager'); // NOT org-wide

        $item = $this->createComplianceItem($organization, [
            'expiry_date' => now()->addDays(5)->toDateString(),
            'responsible_person_id' => null,
        ]);

        app(ComplianceAlertService::class)->scan();

        Notification::assertSentTo($owner, ComplianceExpiryNotification::class);
        Notification::assertNotSentTo($projectManager, ComplianceExpiryNotification::class);
    }
}
