<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('sites.view')
            && $this->hasProjectAccess($user, $project);
    }

    public function view(User $user, Site $site): bool
    {
        return $this->sameOrgAsSite($user, $site)
            && $user->hasPermission('sites.view')
            && $this->hasSiteAccess($user, $site);
    }

    /**
     * Creating a site requires access to the PARENT project (a Project
     * Manager assigned to the project may add sites to it; a Site
     * Supervisor, even if assigned to a sibling site, may not).
     */
    public function create(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project)
            && $user->hasPermission('sites.create')
            && $this->hasProjectAccess($user, $project);
    }

    public function update(User $user, Site $site): bool
    {
        return $this->sameOrgAsSite($user, $site)
            && $user->hasPermission('sites.update')
            && $this->hasSiteAccess($user, $site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->sameOrgAsSite($user, $site)
            && $user->hasPermission('sites.delete')
            && $user->hasOrgWideVisibility();
    }

    public function assignUsers(User $user, Site $site): bool
    {
        return $this->sameOrgAsSite($user, $site)
            && $user->hasPermission('sites.update')
            && ($user->hasOrgWideVisibility() || $site->project->isUserAssigned($user->id));
    }

    /**
     * A user has access to a site if they have org-wide visibility, are
     * assigned to the PARENT project (Project Manager tier), or are
     * assigned directly to the site itself (Site Supervisor tier).
     */
    private function hasSiteAccess(User $user, Site $site): bool
    {
        return $user->hasOrgWideVisibility()
            || $site->project->isUserAssigned($user->id)
            || $site->isUserAssigned($user->id);
    }

    private function hasProjectAccess(User $user, Project $project): bool
    {
        return $user->hasOrgWideVisibility() || $project->isUserAssigned($user->id);
    }

    private function sameOrganization(User $user, Project $project): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $project->organization_id;
    }

    private function sameOrgAsSite(User $user, Site $site): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $site->organization_id;
    }
}
