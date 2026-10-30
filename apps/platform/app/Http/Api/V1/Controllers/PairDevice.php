<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Actions\RedeemPairingCode;
use App\Domain\Tenancy\Exceptions\PairingCodeRejected;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Api\V1\Requests\PairDeviceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * A new till trades a one-time pairing code for its device token. Outside
 * auth:sanctum (the till has no token yet) and strictly rate limited; every
 * refusal is the same 422, with the reason only in the security log.
 */
final class PairDevice
{
    public function __invoke(PairDeviceRequest $request, RedeemPairingCode $redeem): JsonResponse
    {
        try {
            $enrolment = $redeem->handle((string) $request->string('code'));
        } catch (PairingCodeRejected $e) {
            Log::channel('security')->warning("api.device_pairing_{$e->reason}", ['ip' => $request->ip()]);

            return response()->json([
                'message' => 'This code can’t be used. Ask for a new one.',
                'errors' => ['code' => ['This code can’t be used. Ask for a new one.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $device = $enrolment->device;
        $tenant = Tenant::query()->findOrFail($device->tenant_id);
        $branch = $tenant->run(fn () => Branch::query()->findOrFail($device->branch_id));

        Log::channel('security')->info('api.device_paired', ['tenant_id' => $tenant->getTenantKey(), 'device_id' => $device->id, 'ip' => $request->ip()]);

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name, 'till_code' => $device->tillCode()],
            'business' => ['name' => (string) $tenant->getAttribute('name')],
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'business_type' => $branch->business_type->value,
                'terminal_mode' => $branch->business_type->terminalMode()->value,
            ],
            'token' => $enrolment->plainTextToken,
            'token_expires_at' => $device->tokens()->latest('id')->value('expires_at')?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }
}
