<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DefaultRoles;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * Clone the system default roles into a newly created organization.
     * Called from OrganizationService::create() inside the same DB
     * transaction so an organization is never left without its baseline
     * roles.
     */
    public function seedDefaultRolesFor(Organization $organization): void
    {
        foreach (DefaultRoles::definitions() as $slug => $definition) {
            $role = Role::create([
                'organization_id' => $organization->id,
                'name' => $definition['name'],
                'slug' => $slug,
                'is_system' => true,
                'org_wide_visibility' => $definition['org_wide_visibility'],
            ]);

            $permissionIds = Permission::whereIn('name', $definition['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }

    /**
     * Assign a role to a user, guaranteeing the role belongs to the same
     * organization as the user (defense in depth against a client passing a
     * role_id belonging to a different organization).
     */
    public function assignRole(User $user, Role $role): void
    {
        abort_unless(
            $role->organization_id === $user->organization_id,
            403,
            'Role does not belong to the same organization as the user.'
        );

        DB::transaction(function () use ($user, $role) {
            $user->roles()->syncWithoutDetaching([
                $role->id => ['organization_id' => $user->organization_id],
            ]);
        });
    }

    public function revokeRole(User $user, Role $role): void
    {
        $user->roles()->detach($role->id);
    }

    /**
     * Replace a custom (non-system) role's permission set.
     */
    public function updatePermissions(Role $role, array $permissionNames): void
    {
        abort_if($role->isTemplate(), 403, 'System role templates cannot be modified directly.');

        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->sync($permissionIds);
    }
}
