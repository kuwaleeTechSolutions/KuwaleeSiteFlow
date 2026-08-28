<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

/**
 * Materials are an organization-level CATALOG (like Workers) — the same
 * "OPC 53 Grade Cement" master entry is shared across all of an org's
 * projects. Stock BALANCES and TRANSACTIONS, which are project/site
 * specific, are governed separately by MaterialTransactionPolicy.
 */
class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('materials.view');
    }

    public function view(User $user, Material $material): bool
    {
        return $this->sameOrganization($user, $material) && $user->hasPermission('materials.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('materials.create');
    }

    public function update(User $user, Material $material): bool
    {
        return $this->sameOrganization($user, $material) && $user->hasPermission('materials.update');
    }

    private function sameOrganization(User $user, Material $material): bool
    {
        return ! $user->is_super_admin && $user->organization_id === $material->organization_id;
    }
}
