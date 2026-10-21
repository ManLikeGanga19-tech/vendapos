<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Who am I? Lets a terminal confirm its pairing and branch after enrolment. */
final class ShowDevice
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $branch = Branch::query()->findOrFail($device->branch_id);

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name],
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'business_type' => $branch->business_type->value,
                'terminal_mode' => $branch->business_type->terminalMode()->value,
            ],
            'token_expires_at' => $device->currentAccessToken()->expires_at?->toIso8601String(),
        ]);
    }
}
