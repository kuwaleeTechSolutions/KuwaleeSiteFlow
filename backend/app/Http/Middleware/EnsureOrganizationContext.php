<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layer #4 of the organization isolation strategy. Runs on every
 * authenticated, organization-scoped API route.
 *
 * - Resolves the organization strictly from the authenticated session
 *   (never from a client-supplied header, query param, or route segment).
 * - Blocks Super Admin credentials from the tenant-scoped API surface, and
 *   vice versa, so the two audiences are architecturally segregated rather
 *   than merely permission-flagged.
 * - Aborts (403) for any user without an active organization membership
 *   (e.g. disabled account, organization suspended).
 */
class EnsureOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        if ($user->is_super_admin) {
            abort(403, 'Super Admin accounts must use the /api/system endpoints.');
        }

        if (! $user->organization_id) {
            abort(403, 'Your account is not associated with an organization.');
        }

        if ($user->status !== 'active') {
            abort(403, 'Your account is not active. Please contact your organization administrator.');
        }

        if ($user->organization && $user->organization->status === 'suspended') {
            abort(403, 'Your organization account is currently suspended.');
        }

        return $next($request);
    }
}
