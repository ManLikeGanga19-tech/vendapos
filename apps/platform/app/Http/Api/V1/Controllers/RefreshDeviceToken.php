<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Actions\IssueDeviceToken;
use App\Domain\Tenancy\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Rolls a still-valid device token over to a new one; the old one lapses after a short overlap. */
final class RefreshDeviceToken
{
    public function __invoke(Request $request, IssueDeviceToken $issueToken): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $token = $issueToken->handle($device, replacingTokenId: (int) $device->currentAccessToken()->getKey());

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }
}
