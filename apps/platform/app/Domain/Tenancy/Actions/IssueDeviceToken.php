<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Device;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final class IssueDeviceToken
{
    /**
     * Issues a fresh, expiring API token for the device; its plain text is
     * available only on the returned object, as only the hash is stored.
     *
     * With $replacingTokenId, that token is not deleted at once: if the reply
     * carrying the new token is lost, the till still holds only the old one.
     * So the old token keeps working for a short overlap (never longer than
     * it had left) and can ask again; asking again replaces any earlier new
     * token the till never received, so a device holds at most two live
     * tokens. The new token's first use retires the old one
     * (RetireReplacedDeviceTokens).
     */
    public function handle(Device $device, ?int $replacingTokenId = null): NewAccessToken
    {
        /** @var list<string> $abilities */
        $abilities = config('vendapos.device_abilities');

        $issue = fn (): NewAccessToken => $device->createToken(
            name: "terminal:{$device->id}",
            abilities: $abilities,
            expiresAt: now()->addHours((int) config('vendapos.device_token_ttl_hours')),
        );

        if ($replacingTokenId === null) {
            return $issue();
        }

        return DB::connection($device->tokens()->getModel()->getConnectionName())->transaction(function () use ($device, $replacingTokenId, $issue) {
            $replaced = $device->tokens()->whereKey($replacingTokenId)->lockForUpdate()->first();

            $device->tokens()->whereKeyNot($replacingTokenId)->delete();

            if ($replaced instanceof PersonalAccessToken) {
                $overlapEnds = now()->addMinutes((int) config('vendapos.device_token_overlap_minutes'));

                if ($replaced->expires_at === null || $replaced->expires_at->greaterThan($overlapEnds)) {
                    $replaced->forceFill(['expires_at' => $overlapEnds])->save();
                }
            }

            return $issue();
        });
    }
}
