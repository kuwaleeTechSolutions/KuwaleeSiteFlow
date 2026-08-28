<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Apply to every tenant-owned Eloquent model. Adds:
 *  - the `organization()` relationship
 *  - the global OrganizationScope (auto-filters all queries)
 *  - a creating hook that stamps organization_id from the authenticated
 *    user when the caller did not explicitly set one, so it is never
 *    possible to silently create a record with a client-supplied,
 *    mismatched organization_id.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            $user = Auth::user();

            if (! $user || $user->is_super_admin) {
                return;
            }

            if (empty($model->organization_id)) {
                $model->organization_id = $user->organization_id;
                return;
            }

            // Defense in depth: never allow a create payload to stamp a
            // different organization_id than the authenticated user's own,
            // even if a controller forgot to strip it from the request.
            abort_unless(
                (int) $model->organization_id === (int) $user->organization_id,
                403,
                'You are not authorized to perform this action.'
            );
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
