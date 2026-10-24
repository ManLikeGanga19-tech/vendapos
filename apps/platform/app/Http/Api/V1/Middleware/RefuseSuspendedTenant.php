<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For API routes a suspended business may not use, such as starting a new
 * mobile-money payment. Runs after device.bound, inside the device's tenant.
 * Order sync, payment status and token routes do not use it, so the tills
 * keep working while suspended.
 */
final class RefuseSuspendedTenant
{
    public const string CODE = 'tenant_suspended';

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || ! $tenant->isActive()) {
            return response()->json([
                'message' => 'This account is suspended.',
                'code' => self::CODE,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
