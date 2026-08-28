<?php

namespace App\Policies;

use App\Models\DailyReport;
use App\Models\DailyReportPhoto;
use App\Models\User;

class DailyReportPhotoPolicy
{
    /**
     * Uploading a photo to a report follows the exact same gate as editing
     * the report itself (creator/org-wide + editable state + site access).
     */
    public function upload(User $user, DailyReport $report): bool
    {
        return app(DailyReportPolicy::class)->update($user, $report)
            && $user->hasPermission('documents.upload');
    }

    public function view(User $user, DailyReportPhoto $photo): bool
    {
        $report = $photo->dailyReport;

        return ! $user->is_super_admin
            && $user->organization_id === $photo->organization_id
            && $user->hasPermission('daily_reports.view')
            && app(DailyReportPolicy::class)->view($user, $report);
    }

    public function delete(User $user, DailyReportPhoto $photo): bool
    {
        $report = $photo->dailyReport;

        return ! $user->is_super_admin
            && $user->organization_id === $photo->organization_id
            && $user->hasPermission('documents.delete')
            && $report->isEditable()
            && ($user->hasOrgWideVisibility() || $photo->uploaded_by === $user->id);
    }
}
