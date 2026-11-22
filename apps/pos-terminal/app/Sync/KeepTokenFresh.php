<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\Exceptions\TillNotAuthorised;
use App\Api\Exceptions\TokenStillValid;
use App\Api\VendaApi;
use App\Till\Till;

/**
 * Swaps the device token before it runs out. A token that already expired
 * (till switched off overnight) is renewed within the server's grace window;
 * if renewal is refused, the till has been removed.
 */
final class KeepTokenFresh
{
    public function __construct(private readonly VendaApi $api, private readonly Till $till) {}

    /** Throws TillNotAuthorised when the server will not renew the token. */
    public function handle(): void
    {
        $expiresAt = $this->till->identity()?->token_expires_at;

        if ($expiresAt === null) {
            return;
        }

        if ($expiresAt->isPast()) {
            $this->renew();

            return;
        }

        if (now()->diffInHours($expiresAt) < (int) config('terminal.token_refresh_hours_left')) {
            $this->till->storeToken($this->api->refreshToken());
        }
    }

    /**
     * Renew after the server refused the token, or after it expired by our clock.
     */
    public function renew(): void
    {
        try {
            $this->till->storeToken($this->api->renewToken());
        } catch (TokenStillValid) {
            // Our clock ran ahead of the server's: the token still works, so refresh it instead.
            $this->till->storeToken($this->api->refreshToken());
        }
    }
}
