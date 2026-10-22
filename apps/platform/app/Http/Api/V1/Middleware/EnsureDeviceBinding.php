<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Middleware;

use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds an API request to the device that owns the token, then scopes it to
 * that device's tenant. The tenant always comes from the device record,
 * never from anything the client sends.
 */
final class EnsureDeviceBinding
{
    public const string HEADER = 'X-Device-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user('sanctum');

        if (! $device instanceof Device) {
            return $this->reject($request, 'token_not_device');
        }

        if ($device->isRevoked()) {
            return $this->reject($request, 'device_revoked', $device);
        }

        if (! hash_equals($device->id, (string) $request->header(self::HEADER))) {
            return $this->reject($request, 'token_device_mismatch', $device);
        }

        $tenant = Tenant::find($device->tenant_id);

        if (! $tenant instanceof Tenant) {
            return $this->reject($request, 'tenant_missing', $device);
        }

        $this->markSeen($device);

        tenancy()->initialize($tenant);

        try {
            return $next($request);
        } finally {
            // Octane reuses workers: never let one request's tenant leak into the next.
            tenancy()->end();
        }
    }

    /** Shown to staff as "last seen"; written at most once a minute so busy tills don't write on every request. */
    private function markSeen(Device $device): void
    {
        if ($device->last_seen_at === null || $device->last_seen_at->lessThanOrEqualTo(now()->subMinute())) {
            Device::query()->whereKey($device->id)->update(['last_seen_at' => now()]);
        }
    }

    private function reject(Request $request, string $reason, ?Device $device = null): Response
    {
        Log::channel('security')->warning("api.{$reason}", [
            'device_id' => $device?->id,
            'tenant_id' => $device?->tenant_id,
            'claimed_device_id' => $request->header(self::HEADER),
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
    }
}
