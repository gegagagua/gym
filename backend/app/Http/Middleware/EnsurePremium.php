<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** 402 + კოდი — მობაილი ამ კოდზე paywall-ს ხსნის */
class EnsurePremium
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->hasPremium()) {
            return response()->json([
                'message' => __('Premium subscription required.'),
                'code' => 'premium_required',
            ], 402);
        }

        return $next($request);
    }
}
