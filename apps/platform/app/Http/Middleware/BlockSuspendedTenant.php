<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner panel: a suspended tenant's domain shows only a notice (in the
 * owner's language) with what still works and the deletion date. Sign-in
 * and every page, Livewire requests included, are blocked.
 */
class BlockSuspendedTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || $tenant->isActive()) {
            return $next($request);
        }

        app()->setLocale($tenant->preferredLocale());

        return response()->view('owner.suspended', ['tenant' => $tenant], Response::HTTP_FORBIDDEN);
    }
}
