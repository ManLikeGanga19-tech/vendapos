<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Owner panel speaks the language the signed-in person picked, or else
 * the business's own (also on the sign-in page). Octane resets the locale
 * after every request, so it never carries over to the next one.
 */
class SetOwnerPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = Filament::auth()->user();
        $tenant = tenant();

        $locale = match (true) {
            $staff instanceof Staff && in_array($staff->locale, Tenant::LOCALES, true) => $staff->locale,
            $tenant instanceof Tenant => $tenant->preferredLocale(),
            default => 'en',
        };

        app()->setLocale($locale);

        return $next($request);
    }
}
