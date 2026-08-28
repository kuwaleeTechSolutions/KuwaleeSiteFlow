<?php

namespace App\Policies;

use App\Models\Measurement;
use App\Models\Site;
use App\Models\User;

class MeasurementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('measurements.view');
    }

    public function view(User $user, Measurement $measurement): bool
    {
        return $this->sameOrganization($user, $measurement)
            && $user->hasPermission('measurements.view')
            && $this->hasSiteAccess($user, $measurement->site);
    }

    public function createForSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $site->organization_id
            && $user->hasPermission('measurements.create')
            && $this->hasSiteAccess($user, $site);
    }

    /**
     * A measurement is editable ONLY while in draft — once submitted, it
     * must go through approve/reject, never a silent direct edit (brief
     * §20 workflow: Draft -> Submitted -> Approved / Rejected).
     */
    public function update(User $user, Measurement $measurement): bool
    {
        return $this->sameOrganization($user, $measurement)
            && $user->hasPermission('measurements.update')
            && $measurement->isEditable()
            && ($user->hasOrgWideVisibility() || $measurement->created_by === $user->id)
            && $this->hasSiteAccess($user, $measurement->site);
    }

    public function submit(User $user, Measurement $measurement): bool
    {
        return $this->update($user, $measurement);
    }

    /**
     * Approve/reject requires 'measurements.approve' + site access, and —
     * consistent with the Daily Report approval pattern — denies
     * self-approval by default unless the organization has explicitly
     * opted in via settings.allow_self_approval.
     */
    public function approve(User $user, Measurement $measurement): bool
    {
        if ($measurement->status !== 'submitted') {
            return false;
        }

        if (! $this->sameOrganization($user, $measurement) || ! $user->hasPermission('measurements.approve')) {
            return false;
        }

        if (! $this->hasSiteAccess($user, $measurement->site)) {
            return false;
        }

        $isSelfApproval = $measurement->created_by === $user->id;
        $selfApprovalAllowed = (bool) $user->organization?->setting('allow_self_approval', false);

        return ! $isSelfApproval || $selfApprovalAllowed;
    }

    public function reject(User $user, Measurement $measurement): bool
    {
        return $this->approve($user, $measurement);
    }

    /**
     * A brand new "correction" measurement referencing an already-approved
     * one is authorized the same as creating any other measurement for
     * that site — the linkage (revises_measurement_id) is what makes it a
     * correction, not a special permission.
     */
    public function reviseForSite(User $user, Site $site): bool
    {
        return $this->createForSite($user, $site);
    }

    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, Measurement $measurement): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $measurement->organization_id;
    }
}
