<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\Exceptions\TokenStillValid;
use App\Api\VendaApi;
use App\Models\SyncState;
use App\Security\KeyringUnavailable;
use App\Till\Till;
use Illuminate\Support\Facades\Log;

/**
 * One background round: keep the token fresh, then pull device status, staff
 * and menu. Offline is normal and only noted. The till is marked removed only
 * when the server refuses to renew its token, never on a single 401.
 */
final class SyncTill
{
    public const string LAST_SUCCESS = 'sync.last_success_at';

    public const string LAST_ERROR = 'sync.last_error';

    public const string ACCOUNT_STATUS = 'account.status';

    /** Where the window listens for "sync now" (public values only), as JSON; null while the server has it off. */
    public const string REALTIME = 'realtime.config';

    public function __construct(
        private readonly Till $till,
        private readonly VendaApi $api,
        private readonly KeepTokenFresh $token,
        private readonly SyncStaff $staff,
        private readonly SyncCatalogue $catalogue,
    ) {}

    /** Whether the whole round completed. */
    public function handle(): bool
    {
        if (! $this->till->isPaired()) {
            return false;
        }

        try {
            $this->round();

            return true;
        } catch (TillNotAuthorised) {
            $this->recoverAccess();
        } catch (ApiUnavailable|ApiRejected|TokenStillValid|ApiNotConfigured|KeyringUnavailable $e) {
            $this->noteError($e);
        }

        return false;
    }

    private function round(): void
    {
        $this->token->handle();

        $device = $this->api->device();
        $this->till->identity()?->update([
            'device_name' => $device->deviceName,
            'branch_name' => $device->branch->name,
            'terminal_mode' => $device->branch->terminalMode,
        ]);
        SyncState::write(self::ACCOUNT_STATUS, $device->accountStatus);
        SyncState::write(self::REALTIME, $device->realtime === null ? null : (string) json_encode($device->realtime));

        $this->staff->handle();
        $this->catalogue->handle();

        SyncState::write(self::LAST_SUCCESS, now()->toIso8601String());
        SyncState::write(self::LAST_ERROR, null);
    }

    /** A request was refused: the token may have expired early. Only a refused renewal removes the till. */
    private function recoverAccess(): void
    {
        try {
            $this->token->renew();
        } catch (TillNotAuthorised) {
            Log::warning('till.removed_by_server');
            $this->till->markRemoved();
        } catch (ApiUnavailable|ApiRejected|TokenStillValid|ApiNotConfigured|KeyringUnavailable $e) {
            $this->noteError($e);
        }
    }

    private function noteError(\Throwable $e): void
    {
        SyncState::write(self::LAST_ERROR, $e::class);
        Log::info('till.sync_failed', ['error' => $e::class, 'message' => $e->getMessage()]);
    }
}
