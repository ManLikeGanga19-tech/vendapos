<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the {locale} route prefix to the request so every string, date and
 * generated URL on the page is rendered server-side in that language.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $locale = $route?->parameter('locale');

        if (! is_string($locale) || ! array_key_exists($locale, config('marketing.locales'))) {
            abort(404);
        }

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        // Controllers and views never receive the prefix as an argument.
        $route->forgetParameter('locale');

        return $next($request);
    }
}
