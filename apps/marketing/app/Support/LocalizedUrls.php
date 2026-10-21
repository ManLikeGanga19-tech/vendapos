<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;

final class LocalizedUrls
{
    /**
     * The current page's URL in every configured locale, keyed by locale code.
     * Feeds the language switcher and the hreflang alternates.
     *
     * @return array<string, string>
     */
    public static function forRequest(Request $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route || $route->getName() === null) {
            return [];
        }

        // Only real URI segments: Route::view() also stores view/status as parameters.
        $parameters = Arr::only($route->parameters(), $route->parameterNames());

        /** @var array<string, string> $locales */
        $locales = config('marketing.locales');
        $urls = [];

        foreach (array_keys($locales) as $locale) {
            $urls[$locale] = route($route->getName(), ['locale' => $locale, ...$parameters]);
        }

        return $urls;
    }
}
