<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use App\Models\WorkerAttendance;

class WorkerAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('labour.view');
    }

    public function view(User $user, WorkerAttendance $attendance): bool
    {
        return $this->sameOrganization($user, $attendance)
            && $user->hasPermission('labour.view')
            && $this->hasSiteAccess($user, $attendance->site);
    }

    public function markForSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $site->organization_id
            && $user->hasPermission('labour.attendance')
            && $this->hasSiteAccess($user, $site);
    }

    public function update(User $user, WorkerAttendance $attendance): bool
    {
        return $this->sameOrganization($user, $attendance)
            && $user->hasPermission('labour.attendance')
            && $this->hasSiteAccess($user, $attendance->site);
    }

    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, WorkerAttendance $attendance): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $attendance->organization_id;
    }
}
