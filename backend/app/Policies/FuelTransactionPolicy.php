<?php

namespace App\Policies;

use App\Models\FuelTransaction;
use App\Models\Site;
use App\Models\User;

class FuelTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fuel.view');
    }

    public function view(User $user, FuelTransaction $transaction): bool
    {
        return $this->sameOrganization($user, $transaction)
            && $user->hasPermission('fuel.view')
            && $this->hasSiteAccess($user, $transaction->site);
    }

    public function createForSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $site->organization_id
            && $user->hasPermission('fuel.create')
            && $this->hasSiteAccess($user, $site);
    }

    /**
     * Once reviewed, a fuel transaction is immutable at this layer — a
     * correction requires a fresh entry, not a silent edit (consistent with
     * the measurement/bill approval-locks-editing pattern used elsewhere).
     */
    public function update(User $user, FuelTransaction $transaction): bool
    {
        if ($transaction->isReviewed()) {
            return false;
        }

        return $this->sameOrganization($user, $transaction)
            && $user->hasPermission('fuel.update')
            && ($user->hasOrgWideVisibility() || $transaction->recorded_by === $user->id)
            && $this->hasSiteAccess($user, $transaction->site);
    }

    public function review(User $user, FuelTransaction $transaction): bool
    {
        if ($transaction->isReviewed()) {
            return false;
        }

        return $this->sameOrganization($user, $transaction)
            && $user->hasPermission('fuel.approve')
            && $this->hasSiteAccess($user, $transaction->site);
    }

    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, FuelTransaction $transaction): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $transaction->organization_id;
    }
}
