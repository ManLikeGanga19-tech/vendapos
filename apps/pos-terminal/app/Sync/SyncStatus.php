<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\Exceptions\ApiUnavailable;
use App\Models\Shift;
use App\Models\SyncState;
use Carbon\CarbonImmutable;

/** What the status bar shows, read from the notes the last sync rounds left. */
final readonly class SyncStatus
{
    public const string ONLINE = 'online';

    public const string OFFLINE = 'offline';

    public const string NEVER = 'never';

    public function __construct(
        public string $connection,
        public ?CarbonImmutable $lastSuccessAt,
        public bool $accountSuspended,
        // Drawer shifts the server refused: they need the owner, and are never resent on their own.
        public int $refusedShifts = 0,
    ) {}

    public static function current(): self
    {
        $lastSuccess = SyncState::read(SyncTill::LAST_SUCCESS);
        $lastSuccessAt = $lastSuccess === null ? null : CarbonImmutable::parse($lastSuccess);
        $lastError = SyncState::read(SyncTill::LAST_ERROR);

        // Offline when the last round could not reach the server, or no round has
        // succeeded for three intervals (the worker may be stuck).
        $stale = $lastSuccessAt?->lt(now()->subSeconds(3 * (int) config('terminal.sync_interval_seconds')));

        $connection = match (true) {
            $lastError === ApiUnavailable::class, $stale === true => self::OFFLINE,
            $lastSuccessAt === null => self::NEVER,
            default => self::ONLINE,
        };

        return new self(
            connection: $connection,
            lastSuccessAt: $lastSuccessAt,
            accountSuspended: SyncState::read(SyncTill::ACCOUNT_STATUS) === 'suspended',
            refusedShifts: Shift::query()->where('sync_status', Shift::REJECTED)->count(),
        );
    }
}
