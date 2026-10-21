<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Device;
use Laravel\Sanctum\NewAccessToken;

final class IssueDeviceToken
{
    /**
     * Issues a fresh, expiring API token for the device; its plain text is
     * available only on the returned object, as only the hash is stored.
     * With $replacingTokenId, that token is revoked so a device never holds
     * more than one live token.
     */
    public function handle(Device $device, ?int $replacingTokenId = null): NewAccessToken
    {
        /** @var list<string> $abilities */
        $abilities = config('vendapos.device_abilities');

        $token = $device->createToken(
            name: "terminal:{$device->id}",
            abilities: $abilities,
            expiresAt: now()->addHours((int) config('vendapos.device_token_ttl_hours')),
        );

        if ($replacingTokenId !== null) {
            $device->tokens()->whereKey($replacingTokenId)->delete();
        }

        return $token;
    }
}
