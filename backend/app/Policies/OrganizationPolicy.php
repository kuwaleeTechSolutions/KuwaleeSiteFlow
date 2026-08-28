<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Viewing/updating one's OWN organization's profile & settings
     * (currency, timezone, branding, etc.) — distinct from Super Admin's
     * system-wide organization management, which lives behind /api/system
     * and is authorized separately (organization.manage is scoped to a
     * user's own organization only).
     */
    public function view(User $user, Organization $organization): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $organization->id;
    }

    public function update(User $user, Organization $organization): bool
    {
        return ! $user->is_super_admin
            && $user->organization_id === $organization->id
            && $user->hasPermission('organization.manage');
    }
}
