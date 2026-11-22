<?php

declare(strict_types=1);

namespace App\Staff;

use App\Models\PinAttempt;
use App\Models\StaffMember;
use App\Security\SecretBox;
use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * Checks a PIN on the till, offline, against the hash the server sent. After
 * too many wrong PINs in a row that person is locked on this till for a few
 * minutes; a right PIN clears the count.
 */
final class CheckPin
{
    public function __construct(private readonly SecretBox $secrets) {}

    public function handle(StaffMember $staff, #[SensitiveParameter] string $pin): PinOutcome
    {
        $attempt = PinAttempt::query()->find($staff->id);

        if ($attempt?->locked_until?->isFuture()) {
            return PinOutcome::locked($attempt->locked_until);
        }

        // password_verify, not Hash::check: it accepts whichever algorithm the server hashed with.
        if (password_verify($pin, $this->secrets->decrypt($staff->pin_hash_sealed))) {
            $attempt?->delete();

            return PinOutcome::accepted();
        }

        // A lock that ran out starts a fresh count.
        $previous = $attempt !== null && $attempt->locked_until === null ? $attempt->failures : 0;
        $failures = $previous + 1;
        $maxAttempts = (int) config('terminal.pin_max_attempts');

        if ($failures >= $maxAttempts) {
            $until = CarbonImmutable::now()->addMinutes((int) config('terminal.pin_lock_minutes'));
            PinAttempt::query()->updateOrCreate(['staff_id' => $staff->id], ['failures' => $failures, 'locked_until' => $until]);

            return PinOutcome::locked($until);
        }

        PinAttempt::query()->updateOrCreate(['staff_id' => $staff->id], ['failures' => $failures, 'locked_until' => null]);

        return PinOutcome::wrong($maxAttempts - $failures);
    }
}
