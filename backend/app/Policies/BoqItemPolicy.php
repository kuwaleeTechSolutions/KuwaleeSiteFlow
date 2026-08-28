<?php

namespace App\Policies;

use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;

/**
 * NOTE: The brief's canonical permission catalogue (§5) has no dedicated
 * "boq.*" group — BOQ is treated as part of the financial/billing domain,
 * gated by 'billing.view' / 'billing.create' / 'billing.update'. This is a
 * documented interpretation (see README) since BOQ directly defines
 * contract value, the foundation billing is built on.
 */
class BoqItemPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('billing.view')
            && $this->hasProjectAccess($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('billing.create')
            && $this->hasProjectAccess($user, $project);
    }

    /**
     * "Revising" a BOQ item is authorized the same as creating one — the
     * operation itself never edits a row (immutability is enforced at the
     * model layer), it always inserts new rows under a new revision.
     */
    public function revise(User $user, Project $project): bool
    {
        return $this->create($user, $project);
    }

    private function hasProjectAccess(User $user, Project $project): bool
    {
        return $user->hasOrgWideVisibility() || $project->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, Project $project): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $project->organization_id;
    }
}
