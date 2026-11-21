<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\CashUp\Actions\FlagShift;
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
            'device' => ['id' => $device->id, 'name' => $device->name, 'till_code' => $device->tillCode()],
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'business_type' => $branch->business_type->value,
                'terminal_mode' => $branch->business_type->terminalMode()->value,
            ],
            'token_expires_at' => $device->currentAccessToken()->expires_at?->toIso8601String(),
            // "suspended": keep selling and syncing, but new M-Pesa requests are refused, so offer cash.
            'account_status' => tenant('status'),
            // Cash-up: how far a drawer count may be off before closing needs a manager's PIN.
            'cash_up' => ['variance_allowed_cents' => FlagShift::allowedCents()],
            'realtime' => $this->realtime($device),
        ]);
    }

    /**
     * Where to listen for "sync now", or null while broadcasting is off.
     * Subscriptions are signed at POST /api/broadcasting/auth.
     *
     * @return array{key: string, host: string, port: int, scheme: string, channel: string, event: string}|null
     */
    private function realtime(Device $device): ?array
    {
        $key = config('broadcasting.connections.reverb.key');
        $host = config('vendapos.realtime.host');

        if (config('broadcasting.default') !== 'reverb' || ! is_string($key) || ! is_string($host)) {
            return null;
        }

        return [
            'key' => $key,
            'host' => $host,
            'port' => (int) config('vendapos.realtime.port'),
            'scheme' => (string) config('vendapos.realtime.scheme'),
            'channel' => "till.{$device->id}",
            'event' => 'till.sync',
        ];
    }
}
