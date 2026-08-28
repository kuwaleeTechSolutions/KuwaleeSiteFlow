<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

/**
 * Workers are an ORGANIZATION-level resource pool (not owned by a single
 * project — the same worker may be deployed across multiple sites over
 * time), so access here is permission + organization match only. Financial
 * visibility (daily_wage) is handled separately at the Resource layer, not
 * in this Policy, since a user may legitimately be authorized to VIEW a
 * worker's basic profile without being authorized to see their WAGE.
 */
class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('labour.view');
    }

    public function view(User $user, Worker $worker): bool
    {
        return $this->sameOrganization($user, $worker) && $user->hasPermission('labour.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('labour.create');
    }

    public function update(User $user, Worker $worker): bool
    {
        return $this->sameOrganization($user, $worker) && $user->hasPermission('labour.update');
    }

    private function sameOrganization(User $user, Worker $worker): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $worker->organization_id;
    }
}
