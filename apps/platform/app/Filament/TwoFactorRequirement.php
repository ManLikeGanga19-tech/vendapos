<?php

declare(strict_types=1);

namespace App\Filament;

/**
 * Whether the Admin and Owner panels make everyone sign in with an
 * authenticator app. Always yes, except in local development when
 * VENDAPOS_REQUIRE_MFA is switched off: every other environment ignores
 * the switch, so a stray setting can never weaken a real server.
 *
 * Filament reads this once, when the panels register their routes.
 */
final class TwoFactorRequirement
{
    public static function applies(): bool
    {
        if (! app()->isLocal()) {
            return true;
        }

        return (bool) config('vendapos.require_mfa', true);
    }

    /**
     * The panel's "authenticator set up" check for routes we add ourselves,
     * which Filament does not cover.
     *
     * @return list<string>
     */
    public static function middleware(string $requiredMiddleware): array
    {
        return self::applies() ? [$requiredMiddleware] : [];
    }
}
