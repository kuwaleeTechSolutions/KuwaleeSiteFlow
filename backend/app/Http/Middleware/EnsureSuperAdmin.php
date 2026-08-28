<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the entire /api/system/* surface. Physically and logically separate
 * from `EnsureOrganizationContext` (used on tenant routes) — a tenant user's
 * session, no matter what permissions their role holds, can never satisfy
 * this middleware, and a Super Admin's session can never pass
 * EnsureOrganizationContext. The two audiences are architecturally
 * segregated rather than merely permission-flagged.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin) {
            abort(403, 'This action is restricted to system administrators.');
        }

        return $next($request);
    }
}
