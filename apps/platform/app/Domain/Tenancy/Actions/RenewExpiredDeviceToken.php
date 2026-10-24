<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\DeviceTokenNotRenewable;
use App\Domain\Tenancy\Exceptions\DeviceTokenStillValid;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Swaps a device token that expired within the grace window for a fresh
 * one, so a till that was closed or offline past its token's lifetime can
 * sync again without re-enrolment. The old token's row is locked while it
 * is checked and deleted, so two renewals racing with one token yield one
 * new token: the loser finds the row gone.
 */
final class RenewExpiredDeviceToken
{
    public function __construct(private readonly IssueDeviceToken $issueToken) {}

    /**
     * @throws DeviceTokenNotRenewable
     * @throws DeviceTokenStillValid
     */
    public function handle(string $plainTextToken, string $claimedDeviceId): NewAccessToken
    {
        // Device tokens are always "{id}|{secret}"; anything else was not issued by us.
        if (preg_match('/^(\d{1,18})\|(.+)$/', $plainTextToken, $parts) !== 1) {
            throw new DeviceTokenNotRenewable('token_malformed');
        }

        /** @var class-string<PersonalAccessToken> $tokenModel */
        $tokenModel = Sanctum::$personalAccessTokenModel;

        return DB::connection((new $tokenModel)->getConnectionName())->transaction(function () use ($tokenModel, $parts, $claimedDeviceId) {
            $token = $tokenModel::query()->whereKey((int) $parts[1])->lockForUpdate()->first();

            if (! $token instanceof PersonalAccessToken || ! hash_equals($token->token, hash('sha256', $parts[2]))) {
                throw new DeviceTokenNotRenewable('token_unknown');
            }

            $device = $token->tokenable;

            if (! $device instanceof Device) {
                throw new DeviceTokenNotRenewable('token_not_device');
            }

            $this->ensureRenewable($token, $device, $claimedDeviceId);

            return $this->issueToken->handle($device, replacingTokenId: (int) $token->getKey());
        });
    }

    private function ensureRenewable(PersonalAccessToken $token, Device $device, string $claimedDeviceId): void
    {
        if (! hash_equals($device->id, $claimedDeviceId)) {
            throw new DeviceTokenNotRenewable('token_device_mismatch', $device->id);
        }

        if ($device->isRevoked()) {
            throw new DeviceTokenNotRenewable('device_revoked', $device->id);
        }

        if (! Tenant::query()->whereKey($device->tenant_id)->exists()) {
            throw new DeviceTokenNotRenewable('tenant_missing', $device->id);
        }

        $expiresAt = $token->expires_at;

        // Device tokens always expire; one that never does is not a device token we issued.
        if ($expiresAt === null) {
            throw new DeviceTokenNotRenewable('token_never_expires', $device->id);
        }

        if ($expiresAt->isFuture()) {
            throw new DeviceTokenStillValid;
        }

        if ($expiresAt->lessThan(now()->subDays((int) config('vendapos.device_token_grace_days')))) {
            throw new DeviceTokenNotRenewable('grace_expired', $device->id);
        }
    }
}
