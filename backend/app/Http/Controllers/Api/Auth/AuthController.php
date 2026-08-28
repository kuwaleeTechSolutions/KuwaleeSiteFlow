<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    /**
     * Cookie-based SPA login. The frontend must first call
     * GET /sanctum/csrf-cookie before this endpoint (handled automatically
     * by axios + Sanctum's CSRF cookie convention). We never issue a bearer
     * token here — the session cookie IS the credential.
     */
    public function login(LoginRequest $request)
    {
        $throttleKey = strtolower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) env('LOGIN_THROTTLE_ATTEMPTS', 5))) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $query = User::query()->where('email', $request->input('email'));

        if ($request->filled('organization_slug')) {
            $query->whereHas('organization', fn ($q) => $q->where('slug', $request->input('organization_slug')));
        }

        $user = $query->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, (int) env('LOGIN_THROTTLE_DECAY_SECONDS', 60));

            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active. Please contact your administrator.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: false);
        // Regenerate the session id post-login to prevent session fixation.
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLog->log('auth.login', $user, $user);

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => new UserResource($user->load('roles.permissions')),
        ]);
    }

    public function logout()
    {
        $user = Auth::user();
        $this->auditLog->log('auth.logout', $user, $user);

        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function me()
    {
        $user = Auth::user()->load('roles.permissions', 'organization');

        return response()->json([
            'success' => true,
            'data' => array_merge(
                (new UserResource($user))->resolve(),
                [
                    'permissions' => $user->permissionNames(),
                    'organization' => $user->organization ? [
                        'id' => $user->organization->uuid,
                        'name' => $user->organization->name,
                    ] : null,
                    'is_super_admin' => $user->is_super_admin,
                ]
            ),
        ]);
    }
}
