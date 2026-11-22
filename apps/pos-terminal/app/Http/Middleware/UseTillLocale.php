<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Preference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Shows the till in the language chosen on it: English or Swahili. */
final class UseTillLocale
{
    public const array LOCALES = ['en', 'sw'];

    public function handle(Request $request, Closure $next): Response
    {
        self::apply();

        return $next($request);
    }

    public static function apply(): void
    {
        $locale = Preference::read(Preference::LOCALE);

        if (in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }
    }
}
