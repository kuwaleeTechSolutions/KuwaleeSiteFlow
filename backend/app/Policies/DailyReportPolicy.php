<?php

namespace App\Policies;

use App\Models\DailyReport;
use App\Models\Site;
use App\Models\User;

class DailyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('daily_reports.view');
    }

    public function view(User $user, DailyReport $report): bool
    {
        return $this->sameOrganization($user, $report)
            && $user->hasPermission('daily_reports.view')
            && $this->hasSiteAccess($user, $report->site);
    }

    public function createForSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $site->organization_id
            && $user->hasPermission('daily_reports.create')
            && $this->hasSiteAccess($user, $site);
    }

    /**
     * Only the original creator (or an org-wide role) may edit a report,
     * and only while it is in an editable state (draft or returned).
     * Submitted/approved reports are never directly editable — a returned
     * report must go back through draft editing, and an approved report is
     * immutable at this layer entirely (no "unsubmit" path exists).
     */
    public function update(User $user, DailyReport $report): bool
    {
        return $this->sameOrganization($user, $report)
            && $user->hasPermission('daily_reports.update')
            && $report->isEditable()
            && ($user->hasOrgWideVisibility() || $report->created_by === $user->id)
            && $this->hasSiteAccess($user, $report->site);
    }

    public function submit(User $user, DailyReport $report): bool
    {
        return $this->update($user, $report); // same rule: creator + editable state
    }

    /**
     * Approving/returning requires the approve permission, site/project
     * access, AND — critically — must not be the report's own submitter
     * unless the organization has explicitly opted into self-approval
     * (small-team override). See brief §13: "Do not allow unauthorized
     * users to approve their own reports unless explicitly permitted."
     */
    public function approve(User $user, DailyReport $report): bool
    {
        if ($report->status !== 'submitted') {
            return false;
        }

        if (! $this->sameOrganization($user, $report) || ! $user->hasPermission('daily_reports.approve')) {
            return false;
        }

        if (! $this->hasSiteAccess($user, $report->site)) {
            return false;
        }

        $isSelfApproval = $report->submitted_by === $user->id;
        $selfApprovalAllowed = (bool) $user->organization?->setting('allow_self_approval', false);

        return ! $isSelfApproval || $selfApprovalAllowed;
    }

    public function returnForCorrection(User $user, DailyReport $report): bool
    {
        return $this->approve($user, $report); // identical gate; action differs in controller
    }

    public function delete(User $user, DailyReport $report): bool
    {
        return $this->sameOrganization($user, $report)
            && $user->hasPermission('daily_reports.delete')
            && $user->hasOrgWideVisibility();
    }

    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, DailyReport $report): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $report->organization_id;
    }
}
