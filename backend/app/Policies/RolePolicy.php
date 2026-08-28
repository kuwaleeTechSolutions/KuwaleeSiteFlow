<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->sameOrganization($user, $role) && $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        // System role templates (organization_id === null) are never
        // editable through the tenant API — only cloned copies are.
        if ($role->isTemplate()) {
            return false;
        }

        return $this->sameOrganization($user, $role) && $user->hasPermission('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->isTemplate() || $role->is_system) {
            // System-provisioned roles (even the organization's own copies)
            // cannot be deleted outright to avoid orphaning permission
            // assignments relied upon by default workflows; they can be
            // edited/deactivated instead. Only fully custom roles may be
            // deleted.
            return false;
        }

        return $this->sameOrganization($user, $role) && $user->hasPermission('roles.delete');
    }

    private function sameOrganization(User $user, Role $role): bool
    {
        return ! $user->is_super_admin
            && $role->organization_id !== null
            && $user->organization_id === $role->organization_id;
    }
}
