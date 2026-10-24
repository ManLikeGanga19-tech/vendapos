<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Actions\ListTillStaff;
use App\Domain\Tenancy\Models\Device;
use App\Http\Api\V1\Requests\ShowTillStaffRequest;
use Illuminate\Http\JsonResponse;

/**
 * Who can sign in on this till by PIN, with what they may do. The whole list
 * for the device's branch every time; the till replaces its copy.
 */
final class ShowTillStaff
{
    public function __invoke(ShowTillStaffRequest $request, ListTillStaff $listTillStaff): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        return response()
            ->json([
                'synced_at' => now()->toIso8601String(),
                'staff' => $listTillStaff->handle($device->branch_id),
            ])
            // PIN hashes: never kept by a proxy or browser cache.
            ->header('Cache-Control', 'no-store');
    }
}
