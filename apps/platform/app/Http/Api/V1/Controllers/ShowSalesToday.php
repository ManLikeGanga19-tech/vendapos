<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Orders\Actions\ListSalesToday;
use App\Domain\Tenancy\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/orders/today — the till's own branch's sales of the current
 * business day from every till, newest first, with any void. The till lists
 * them so a cashier can find a sale to void, whichever till made it.
 */
final class ShowSalesToday
{
    public function __invoke(Request $request, ListSalesToday $sales): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        return response()->json(['orders' => $sales->handle($device->branch_id)]);
    }
}
