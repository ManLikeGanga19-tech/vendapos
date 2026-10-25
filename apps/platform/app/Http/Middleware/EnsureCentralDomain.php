<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The Admin panel answers only on VendaPOS's own (central) domains, never on a tenant's. */
class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->getHost(), config('tenancy.central_domains'), true), 404);

        return $next($request);
    }
}
