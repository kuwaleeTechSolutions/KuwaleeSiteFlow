<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\DefaultRoles;
use Illuminate\Database\Seeder;

/**
 * Seeds the global, organization_id=null SYSTEM ROLE TEMPLATES only — these
 * are reference definitions, never assignable to a user directly. Concrete,
 * organization-owned copies are cloned by RoleService::seedDefaultRolesFor()
 * whenever a new organization is provisioned (see
 * Api\System\OrganizationController::store()).
 */
class RoleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DefaultRoles::definitions() as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['organization_id' => null, 'slug' => $slug],
                [
                    'name' => $definition['name'],
                    'is_system' => true,
                    'org_wide_visibility' => $definition['org_wide_visibility'],
                ]
            );

            $permissionIds = Permission::whereIn('name', $definition['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
