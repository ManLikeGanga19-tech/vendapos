<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use VendaPOS\Core\Enums\TerminalMode;

/**
 * For the ticket routes: only tills in a branch that runs table service
 * (the restaurant module) keep open tickets. A quick-sale till of a shop
 * is refused, whatever it sends (T7). Runs after device.bound, inside the
 * device's tenant.
 */
final class RequireTableService
{
    public const string CODE = 'not_table_service';

    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user('sanctum');
        $branch = $device instanceof Device ? Branch::query()->find($device->branch_id) : null;

        if (! $branch instanceof Branch || $branch->business_type->terminalMode() !== TerminalMode::TableService) {
            return response()->json([
                'message' => 'This till’s branch does not run table service.',
                'code' => self::CODE,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
