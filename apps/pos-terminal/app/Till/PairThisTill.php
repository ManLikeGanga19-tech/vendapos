<?php

declare(strict_types=1);

namespace App\Till;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\VendaApi;
use App\Jobs\SyncTillJob;
use App\Security\KeyringUnavailable;
use App\Security\SecretBox;
use App\Sync\SyncTill;

/**
 * Pairs the till with a code from the Owner panel, then runs a first sync
 * (staff, menu, where to listen for live updates). A failed download does not undo the pairing: the background sync
 * picks it up once the till is online.
 */
final class PairThisTill
{
    // Same alphabet and length as the server's IssuePairingCode: no 0/O, 1/I/L or U.
    public const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const int LENGTH = 8;

    public function __construct(
        private readonly VendaApi $api,
        private readonly Till $till,
        private readonly SecretBox $secrets,
        private readonly SyncTill $sync,
    ) {}

    /** "abcd-2345 " becomes "ABCD2345"; the server accepts any case, spaces and dashes too. */
    public static function normalise(string $typed): string
    {
        return strtoupper((string) preg_replace('/[\s-]/', '', $typed));
    }

    public static function isWellFormed(string $code): bool
    {
        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH;
    }

    /**
     * Returns whether the first sync completed too. Throws PairingFailed.
     */
    public function handle(string $typedCode): bool
    {
        $code = self::normalise($typedCode);

        if (! self::isWellFormed($code)) {
            throw new PairingFailed('code_invalid');
        }

        try {
            // Before the request: the server uses up the code as soon as it answers.
            $this->secrets->ensureAvailable();
            $this->till->pair($this->api->pair($code));
        } catch (KeyringUnavailable $e) {
            throw new PairingFailed('keyring', $e);
        } catch (ApiRejected $e) {
            throw new PairingFailed('code_refused', $e);
        } catch (ApiUnavailable $e) {
            throw new PairingFailed($e->isRateLimited() ? 'too_many_tries' : 'offline', $e);
        } catch (ApiNotConfigured $e) {
            throw new PairingFailed('not_configured', $e);
        }

        // A full round, not only staff and menu: it also brings where to listen for
        // live updates, so they work from the first screen instead of after a minute.
        $downloaded = $this->sync->handle();

        SyncTillJob::dispatch()->delay(now()->addSeconds((int) config('terminal.sync_interval_seconds')));

        return $downloaded;
    }
}
