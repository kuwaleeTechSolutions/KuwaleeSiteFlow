<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\Project;
use App\Models\User;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('billing.view');
    }

    public function view(User $user, Bill $bill): bool
    {
        return $this->sameOrganization($user, $bill)
            && $user->hasPermission('billing.view')
            && $this->hasProjectAccess($user, $bill->project);
    }

    public function createForProject(User $user, Project $project): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $project->organization_id
            && $user->hasPermission('billing.create')
            && $this->hasProjectAccess($user, $project);
    }

    /**
     * Editable only in 'draft' — once submitted for certification, items
     * are locked and only certify()/cancel() actions remain available.
     */
    public function update(User $user, Bill $bill): bool
    {
        return $this->sameOrganization($user, $bill)
            && $user->hasPermission('billing.update')
            && $bill->isEditable()
            && $this->hasProjectAccess($user, $bill->project);
    }

    /**
     * Certification is a distinct, more sensitive action than general
     * billing.update — requires 'billing.approve' and denies self-
     * certification by default (same pattern as measurement/daily-report
     * approval), since a bill's creator certifying their own bill defeats
     * the purpose of a certification step.
     */
    public function certify(User $user, Bill $bill): bool
    {
        if ($bill->status !== 'submitted') {
            return false;
        }

        if (! $this->sameOrganization($user, $bill) || ! $user->hasPermission('billing.approve')) {
            return false;
        }

        if (! $this->hasProjectAccess($user, $bill->project)) {
            return false;
        }

        $isSelfCertification = $bill->created_by === $user->id;
        $selfApprovalAllowed = (bool) $user->organization?->setting('allow_self_approval', false);

        return ! $isSelfCertification || $selfApprovalAllowed;
    }

    private function hasProjectAccess(User $user, Project $project): bool
    {
        return $user->hasOrgWideVisibility() || $project->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, Bill $bill): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $bill->organization_id;
    }
}
