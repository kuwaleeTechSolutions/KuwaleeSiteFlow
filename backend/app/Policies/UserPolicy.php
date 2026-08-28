<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->sameOrganization($user, $target)
            && ($user->hasPermission('users.view') || $user->id === $target->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true; // users may always update their own basic profile
        }

        return $this->sameOrganization($user, $target)
            && $user->hasPermission('users.update');
    }

    /**
     * Changing another user's role is treated as a distinct, more sensitive
     * action than a generic profile update (see brief §9 audit examples:
     * "User changed another user's role").
     */
    public function assignRole(User $user, User $target): bool
    {
        return $this->sameOrganization($user, $target)
            && $user->hasPermission('users.update')
            && $user->id !== $target->id; // cannot self-elevate
    }

    public function delete(User $user, User $target): bool
    {
        return $this->sameOrganization($user, $target)
            && $user->hasPermission('users.delete')
            && $user->id !== $target->id; // cannot delete own account via this endpoint
    }

    private function sameOrganization(User $user, User $target): bool
    {
        return ! $user->is_super_admin
            && $target->organization_id !== null
            && $user->organization_id === $target->organization_id;
    }
}
