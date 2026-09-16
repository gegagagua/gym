<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // სპეც. 17 — rate limiting
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perHour(3)->by((string) $request->input('phone')),
            Limit::perHour(20)->by($request->ip()),
        ]);

        RateLimiter::for('sync', fn (Request $request) => Limit::perHour(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('ugc', fn (Request $request) => Limit::perDay(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
