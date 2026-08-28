<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level fast-fail permission check, used as:
 *   Route::post('/projects', ...)->middleware('permission:projects.create');
 *
 * IMPORTANT: this is a UX/performance optimisation (fail fast before
 * touching the database), NOT a replacement for the resource-level Policy
 * check. Controllers MUST still call $this->authorize() against the actual
 * model instance, because a permission alone does not prove the user is
 * allowed to act on THIS specific project/site/document (see Policies).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'You are not authorized to perform this action.');
        }

        return $next($request);
    }
}
