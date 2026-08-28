<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralises project/site user-assignment logic so it is never duplicated
 * (and never silently diverges) across controllers. Every mutation runs
 * inside a DB transaction and re-verifies that every target user belongs to
 * the same organization as the project/site being assigned — defense in
 * depth on top of the Form Request's own `Rule::exists(...)->where(...)`
 * check.
 */
class ProjectAssignmentService
{
    public function assignUsersToProject(Project $project, array $userIds): void
    {
        $this->assertUsersBelongToOrganization($userIds, $project->organization_id);

        DB::transaction(function () use ($project, $userIds) {
            $project->assignedUsers()->syncWithoutDetaching($userIds);
        });
    }

    public function removeUserFromProject(Project $project, User $user): void
    {
        $project->assignedUsers()->detach($user->id);
    }

    public function assignUsersToSite(Site $site, array $userIds): void
    {
        $this->assertUsersBelongToOrganization($userIds, $site->organization_id);

        DB::transaction(function () use ($site, $userIds) {
            $site->assignedUsers()->syncWithoutDetaching($userIds);
        });
    }

    public function removeUserFromSite(Site $site, User $user): void
    {
        $site->assignedUsers()->detach($user->id);
    }

    private function assertUsersBelongToOrganization(array $userIds, int $organizationId): void
    {
        $validCount = User::whereIn('id', $userIds)->where('organization_id', $organizationId)->count();

        abort_unless(
            $validCount === count(array_unique($userIds)),
            403,
            'One or more users do not belong to this organization.'
        );
    }
}
