<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** last_active_at ერთხელ საათში — ყოველ მოთხოვნაზე ჩაწერა ზედმეტი დატვირთვაა */
class TouchLastActive
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($user = $request->user()) {
            $key = "touch:{$user->id}";
            if (! Cache::has($key)) {
                Cache::put($key, 1, now()->addHour());
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }
        }

        return $response;
    }
}
