<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Providers\AppServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum stateful SPA support — required so cookie-based sessions
        // are recognised as "first party" for the configured frontend origins.
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);

        // Register named middleware aliases used across api.php route definitions.
        $middleware->alias([
            'org.context' => EnsureOrganizationContext::class,
            'permission' => CheckPermission::class,
            'super_admin' => EnsureSuperAdmin::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Centralised, consistent JSON error envelope for the whole API.
        // CRITICAL: never leak stack traces, SQL, file paths, or secrets to the
        // client in production — see brief §30 "Error Handling".
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // fall back to default (non-API) rendering
            }

            $status = 500;
            $message = 'An unexpected error occurred. Please try again later.';
            $errors = null;

            if ($e instanceof ValidationException) {
                $status = 422;
                $message = 'The given data was invalid.';
                $errors = $e->errors();
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
                $message = 'Authentication required.';
            } elseif ($e instanceof AuthorizationException) {
                $status = 403;
                $message = 'You are not authorized to perform this action.';
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = match ($status) {
                    404 => 'The requested resource was not found.',
                    429 => 'Too many requests. Please slow down and try again shortly.',
                    default => $e->getMessage() ?: $message,
                };
            }

            if ($status === 500 && config('app.debug')) {
                // Local/staging debugging only — never enabled in production.
                $message = $e->getMessage();
            }

            $payload = ['success' => false, 'message' => $message];
            if ($errors) {
                $payload['errors'] = $errors;
            }

            return response()->json($payload, $status);
        });
    })->create();
