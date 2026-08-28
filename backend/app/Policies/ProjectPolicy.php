<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('projects.view')
            && $this->hasAccess($user, $project);
    }

    /**
     * Creating a new project is an organization-level action — there is no
     * existing assignment to check against yet. The creator is NOT
     * automatically assigned; that must be done explicitly via assignUsers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('projects.update')
            && $this->hasAccess($user, $project);
    }

    /**
     * Deletion is a high-blast-radius action (cascades to sites, and in
     * later phases to daily reports, materials, etc.) — restricted to
     * org-wide-visibility roles (Owner/Admin) regardless of assignment.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('projects.delete')
            && $user->hasOrgWideVisibility();
    }

    /**
     * Managing WHO is assigned to a project is itself restricted to
     * org-wide-visibility roles — an assigned Project Manager cannot grant
     * themselves or others access to additional projects.
     */
    public function assignUsers(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('projects.update')
            && $user->hasOrgWideVisibility();
    }

    private function hasAccess(User $user, Project $project): bool
    {
        return $user->hasOrgWideVisibility() || $project->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, Project $project): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $project->organization_id;
    }
}
