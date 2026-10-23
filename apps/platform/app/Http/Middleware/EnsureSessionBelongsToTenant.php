<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessions live in one central table. Session cookies are host-only, so a
 * browser never sends tenant A's cookie to tenant B, but a copied cookie
 * could be. Stamp each session with the tenant it was started on and start
 * a fresh one whenever it shows up on another tenant's domain.
 */
class EnsureSessionBelongsToTenant
{
    public const KEY = 'vendapos.tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant()?->getTenantKey();
        $session = $request->session();

        if ($session->has(self::KEY) && $session->get(self::KEY) !== $tenantId) {
            $session->invalidate();
        }

        $session->put(self::KEY, $tenantId);

        return $next($request);
    }
}
