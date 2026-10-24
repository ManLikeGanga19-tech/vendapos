<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Actions\RenewExpiredDeviceToken;
use App\Domain\Tenancy\Exceptions\DeviceTokenNotRenewable;
use App\Domain\Tenancy\Exceptions\DeviceTokenStillValid;
use App\Http\Api\V1\Middleware\EnsureDeviceBinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renews a device token that expired within the grace window. It sits
 * outside auth:sanctum because the token it accepts no longer authenticates;
 * every failure is the same 401, with the reason only in the security log.
 */
final class RenewDeviceToken
{
    public function __invoke(Request $request, RenewExpiredDeviceToken $renew): JsonResponse
    {
        $claimedDeviceId = (string) $request->header(EnsureDeviceBinding::HEADER);

        try {
            $token = $renew->handle((string) $request->bearerToken(), $claimedDeviceId);
        } catch (DeviceTokenStillValid) {
            return response()->json(['message' => 'Token is still valid; refresh it with POST /api/v1/device/token.'], Response::HTTP_CONFLICT);
        } catch (DeviceTokenNotRenewable $e) {
            Log::channel('security')->warning("api.token_renewal_{$e->reason}", [
                'device_id' => $e->deviceId,
                'claimed_device_id' => $claimedDeviceId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        Log::channel('security')->info('api.token_renewed', ['device_id' => $claimedDeviceId, 'ip' => $request->ip()]);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }
}
