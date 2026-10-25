<?php

declare(strict_types=1);

namespace App\Staff;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\Exceptions\TokenStillValid;
use App\Api\VendaApi;
use App\Models\StaffMember;
use App\Security\KeyringUnavailable;
use App\Security\SecretBox;
use App\Sync\KeepTokenFresh;
use App\Sync\SyncStaff;
use SensitiveParameter;

/**
 * The signed-in person chooses a new PIN. The current PIN is checked on the
 * till first, so wrong guesses count towards the same lockout as sign-in;
 * then the server checks it again and stores the new one. Needs internet.
 */
final class ChangeMyPin
{
    /** Server reasons the person can act on; anything else is shown as "try again". */
    private const array SERVER_REASONS = ['wrong_pin', 'wrong_length', 'too_easy', 'same_pin'];

    public function __construct(
        private readonly CheckPin $checkPin,
        private readonly VendaApi $api,
        private readonly KeepTokenFresh $token,
        private readonly SecretBox $secrets,
    ) {}

    /** Throws PinChangeFailed. */
    public function handle(StaffMember $staff, #[SensitiveParameter] string $currentPin, #[SensitiveParameter] string $newPin): void
    {
        $outcome = $this->checkPin->handle($staff, $currentPin);

        if (! $outcome->isAccepted()) {
            throw new PinChangeFailed($outcome->result === PinOutcome::LOCKED ? 'locked' : 'wrong_pin');
        }

        if (strlen($newPin) !== $staff->pin_length || ! ctype_digit($newPin)) {
            throw new PinChangeFailed('wrong_length');
        }

        $entry = $this->send($staff, $currentPin, $newPin);

        $staff->update(['pin_hash_sealed' => SyncStaff::row($entry, $this->secrets)['pin_hash_sealed']]);
    }

    /** @return array{id: string, name: string, role: string, permissions: list<string>, pin_hash: string, pin_length: int} */
    private function send(StaffMember $staff, #[SensitiveParameter] string $currentPin, #[SensitiveParameter] string $newPin): array
    {
        try {
            try {
                return $this->api->changePin($staff->id, $currentPin, $newPin);
            } catch (TillNotAuthorised) {
                // A token issued before tills could change PINs lacks the ability: swap it once and retry.
                $this->token->renew();

                return $this->api->changePin($staff->id, $currentPin, $newPin);
            }
        } catch (ApiRejected $e) {
            throw new PinChangeFailed(in_array($e->reason, self::SERVER_REASONS, true) ? (string) $e->reason : 'not_on_server', $e);
        } catch (ApiUnavailable $e) {
            throw new PinChangeFailed($e->isRateLimited() ? 'too_many_tries' : 'offline', $e);
        } catch (TillNotAuthorised|TokenStillValid|ApiNotConfigured|KeyringUnavailable $e) {
            throw new PinChangeFailed('offline', $e);
        }
    }
}
