<?php

namespace App\Policies;

use App\Models\EquipmentUsageLog;
use App\Models\Site;
use App\Models\User;

class EquipmentUsageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('equipment.view');
    }

    public function view(User $user, EquipmentUsageLog $log): bool
    {
        return $this->sameOrganization($user, $log)
            && $user->hasPermission('equipment.view')
            && $this->hasSiteAccess($user, $log->site);
    }

    public function createForSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $site->organization_id
            && $user->hasPermission('equipment.log_usage')
            && $this->hasSiteAccess($user, $site);
    }

    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, EquipmentUsageLog $log): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $log->organization_id;
    }
}
