<?php

namespace App\Services;

use App\Models\ComplianceItem;
use App\Models\User;
use App\Notifications\ComplianceExpiryNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Core scanning logic invoked daily by the ScanComplianceExpiry console
 * command (see routes/console.php for the schedule registration).
 *
 * Duplicate-notification prevention: a compliance item's
 * `last_alert_threshold_days` is only updated (and a notification only
 * sent) when the newly crossed threshold is STRICTLY SMALLER than the
 * last one alerted for — so a daily run never re-sends the same "expires
 * in 30 days" alert every day between the 30-day and 15-day marks.
 */
class ComplianceAlertService
{
    public function scan(): int
    {
        $thresholds = collect(config('documents.expiry_alert_thresholds'))->sortDesc()->values();
        $notifiedCount = 0;

        ComplianceItem::query()->chunkById(200, function ($items) use ($thresholds, &$notifiedCount) {
            foreach ($items as $item) {
                $daysUntilExpiry = $item->daysUntilExpiry();

                $crossedThreshold = $thresholds->first(fn ($t) => $daysUntilExpiry <= $t);

                if ($crossedThreshold === null) {
                    // Not yet within any alert window — ensure status is
                    // 'valid' in case it was previously flagged and the
                    // expiry_date was extended.
                    if ($item->status !== 'valid' || $item->last_alert_threshold_days !== null) {
                        $item->update(['status' => 'valid', 'last_alert_threshold_days' => null]);
                    }

                    continue;
                }

                $newStatus = $crossedThreshold === 0 ? 'expired' : 'expiring';

                $alreadyNotifiedForThisOrTighterThreshold = $item->last_alert_threshold_days !== null
                    && $item->last_alert_threshold_days <= $crossedThreshold;

                if (! $alreadyNotifiedForThisOrTighterThreshold) {
                    $this->notify($item, $crossedThreshold);
                    $notifiedCount++;
                }

                $item->update([
                    'status' => $newStatus,
                    'last_alert_threshold_days' => $crossedThreshold,
                ]);
            }
        });

        return $notifiedCount;
    }

    private function notify(ComplianceItem $item, int $thresholdDays): void
    {
        $recipients = $item->responsible_person_id
            ? User::where('id', $item->responsible_person_id)->get()
            : User::where('organization_id', $item->organization_id)
                ->whereHas('roles', fn ($q) => $q->where('org_wide_visibility', true))
                ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, new ComplianceExpiryNotification($item, $thresholdDays));
    }
}
