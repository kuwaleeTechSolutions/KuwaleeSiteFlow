<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

/**
 * Equipment is an organization-level fleet REGISTRY, visible org-wide to
 * anyone with 'equipment.view' (like Materials/Workers) — but unlike those,
 * registering NEW equipment or deleting it is restricted to org-wide-
 * visibility roles (Owner/Admin), since it represents a capital asset
 * record shared across potentially many projects over its lifetime.
 * Updating an equipment's status/assignment is allowed for a Project
 * Manager currently assigned to its assigned_project as well.
 */
class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('equipment.view');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $this->sameOrganization($user, $equipment) && $user->hasPermission('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('equipment.create') && $user->hasOrgWideVisibility();
    }

    public function update(User $user, Equipment $equipment): bool
    {
        if (! $this->sameOrganization($user, $equipment) || ! $user->hasPermission('equipment.update')) {
            return false;
        }

        return $user->hasOrgWideVisibility()
            || ($equipment->assigned_project_id && $equipment->assignedProject->isUserAssigned($user->id));
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $this->sameOrganization($user, $equipment)
            && $user->hasPermission('equipment.delete')
            && $user->hasOrgWideVisibility();
    }

    private function sameOrganization(User $user, Equipment $equipment): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $equipment->organization_id;
    }
}
