<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class Phase11SecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(config('security.rate_limits.login_per_minute', 5))
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('api-read', fn (Request $request) => [
            Limit::perMinute(config('security.rate_limits.read_per_minute', 120))
                ->by($request->user()?->id ?: $request->ip()),
        ]);

        RateLimiter::for('api-mutation', fn (Request $request) => [
            Limit::perMinute(config('security.rate_limits.mutation_per_minute', 60))
                ->by($request->user()?->id ?: $request->ip()),
        ]);

        RateLimiter::for('api-upload', fn (Request $request) => [
            Limit::perMinute(config('security.rate_limits.upload_per_minute', 20))
                ->by($request->user()?->id ?: $request->ip()),
        ]);

        RateLimiter::for('api-export', fn (Request $request) => [
            Limit::perMinute(config('security.rate_limits.export_per_minute', 20))
                ->by($request->user()?->id ?: $request->ip()),
        ]);
    }
}
