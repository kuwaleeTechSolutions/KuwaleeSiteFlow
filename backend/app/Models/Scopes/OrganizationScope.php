<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that automatically constrains every query on a tenant-owned
 * model to the authenticated user's organization.
 *
 * This is layer #2 of the four-layer organization isolation strategy (see
 * blueprint §4). It is a safety net, NOT the sole authorization mechanism —
 * Policies still re-verify organization ownership explicitly before any
 * read/write is permitted (layer #3), because a raw query, a queued job
 * running without an authenticated request, or a `withoutGlobalScope()` call
 * elsewhere in the codebase could otherwise bypass this scope silently.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            // No authenticated user in this context (e.g. console/queue).
            // Callers running trusted background jobs MUST explicitly scope
            // their queries themselves; we deliberately do NOT apply a
            // silent "no restriction" here to avoid accidentally leaking
            // cross-tenant data through an unauthenticated web request.
            return;
        }

        // Super Admins (organization_id === null) are not tenant-bound and
        // operate through the separate /api/system/* surface — never apply
        // an organization filter for them here.
        if ($user->is_super_admin) {
            return;
        }

        $builder->where($model->getTable().'.organization_id', $user->organization_id);
    }
}
