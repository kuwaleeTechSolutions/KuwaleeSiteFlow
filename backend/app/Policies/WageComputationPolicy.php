<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\WageComputation;

/**
 * 'labour.wages' is treated as a distinct, more sensitive permission than
 * 'labour.attendance' — per brief §4.7/§15, financial/wage visibility must
 * be independently permission-controlled, not implied by the ability to
 * mark attendance.
 */
class WageComputationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('labour.wages');
    }

    public function view(User $user, WageComputation $computation): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $computation->organization_id
            && $user->hasPermission('labour.wages')
            && $this->hasProjectAccess($user, $computation->project);
    }

    public function generateForProject(User $user, Project $project): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $project->organization_id
            && $user->hasPermission('labour.wages')
            && $this->hasProjectAccess($user, $project);
    }

    private function hasProjectAccess(User $user, Project $project): bool
    {
        return $user->hasOrgWideVisibility() || $project->isUserAssigned($user->id);
    }
}
