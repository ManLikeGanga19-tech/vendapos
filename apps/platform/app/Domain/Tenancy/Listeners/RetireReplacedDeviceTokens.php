<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Listeners;

use App\Domain\Tenancy\Models\Device;
use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * A device token's first use proves the till received it, so the tokens it
 * replaced (kept alive for the overlap by IssueDeviceToken) go at once.
 * Sanctum fires this before stamping last_used_at, so an empty stamp means
 * first use and every later request costs no query.
 */
final class RetireReplacedDeviceTokens
{
    public function handle(TokenAuthenticated $event): void
    {
        $token = $event->token;

        if ($token->last_used_at !== null || ! $token->tokenable instanceof Device) {
            return;
        }

        $token->tokenable->tokens()->where('id', '<', $token->getKey())->delete();
    }
}
