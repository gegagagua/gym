<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;

/**
 * ენა ისაზღვრება ამ თანმიმდევრობით:
 *   1. Accept-Language ჰედერი (კლიენტი ყოველთვის აგზავნის)
 *   2. ავტორიზებული მომხმარებლის შენახული locale
 *   3. fallback en
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->hasHeader('Accept-Language')
            ? Locale::fromHeader($request->header('Accept-Language'))
            : ($request->user()?->locale ?? Locale::FALLBACK);

        app()->setLocale(in_array($locale, Locale::SUPPORTED, true) ? $locale : Locale::FALLBACK);

        return $next($request);
    }
}
