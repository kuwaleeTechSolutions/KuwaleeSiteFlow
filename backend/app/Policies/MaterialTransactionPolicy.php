<?php

namespace App\Policies;

use App\Models\MaterialTransaction;
use App\Models\Site;
use App\Models\User;

class MaterialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('materials.view');
    }

    public function view(User $user, MaterialTransaction $transaction): bool
    {
        return $this->sameOrganization($user, $transaction)
            && $user->hasPermission('materials.view')
            && $this->hasSiteAccess($user, $transaction->site);
    }

    /**
     * inward/return/adjustment are gated by the general 'materials.create'
     * permission (they add stock or make a bookkeeping correction);
     * issue/transfer require the MORE SPECIFIC 'materials.issue' /
     * 'materials.transfer' permissions respectively — matching the
     * granularity in the brief's permission catalogue (§5).
     */
    public function createForSite(User $user, Site $site, string $transactionType): bool
    {
        if (! $this->userInSameOrgAsSite($user, $site) || ! $this->hasSiteAccess($user, $site)) {
            return false;
        }

        return match ($transactionType) {
            'issue' => $user->hasPermission('materials.issue'),
            'transfer' => $user->hasPermission('materials.transfer'),
            default => $user->hasPermission('materials.create'), // inward, return, adjustment
        };
    }

    /**
     * Forcing a transaction through despite insufficient stock requires the
     * distinct, highly-restricted 'materials.negative_stock_override'
     * permission — never implied by materials.issue/transfer alone.
     */
    public function overrideNegativeStock(User $user): bool
    {
        return $user->hasPermission('materials.negative_stock_override');
    }

    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function userInSameOrgAsSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $site->organization_id;
    }

    private function sameOrganization(User $user, MaterialTransaction $transaction): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $transaction->organization_id;
    }
}
