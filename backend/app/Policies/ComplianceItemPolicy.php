<?php

namespace App\Policies;

use App\Models\ComplianceItem;
use App\Models\User;

/**
 * Compliance items are treated as an organization-level list (like
 * Materials/Workers/Equipment) — visible to anyone holding 'compliance.view'
 * regardless of the related_entity's project/site, since compliance
 * (insurance, licences, certificates) is typically an organization-wide
 * oversight concern rather than something to hide behind project
 * assignment. This mirrors how the brief frames compliance as owned by
 * "Responsible Person" + org-wide alerting, not per-project ACLs.
 */
class ComplianceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('compliance.view');
    }

    public function view(User $user, ComplianceItem $item): bool
    {
        return $this->sameOrganization($user, $item) && $user->hasPermission('compliance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('compliance.create');
    }

    public function update(User $user, ComplianceItem $item): bool
    {
        return $this->sameOrganization($user, $item) && $user->hasPermission('compliance.update');
    }

    public function delete(User $user, ComplianceItem $item): bool
    {
        return $this->sameOrganization($user, $item) && $user->hasPermission('compliance.delete');
    }

    private function sameOrganization(User $user, ComplianceItem $item): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $item->organization_id;
    }
}
