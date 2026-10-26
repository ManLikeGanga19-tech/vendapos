<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Models\Device;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\FloorArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/floor — the till's own branch's areas and tables in use, in
 * full (a floor plan is small). Empty for branches that do not serve at tables.
 */
final class ShowFloor
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        $areas = FloorArea::query()
            ->where('branch_id', $device->branch_id)
            ->with(['tables' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (FloorArea $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'sort_order' => $area->sort_order,
                'tables' => $area->tables->map(fn (DiningTable $table) => [
                    'id' => $table->id,
                    'name' => $table->name,
                    'seats' => $table->seats,
                    'sort_order' => $table->sort_order,
                ])->values()->all(),
            ])
            ->values()->all();

        return response()->json(['areas' => $areas]);
    }
}
