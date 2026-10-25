<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\PinChangeRefused;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;

/**
 * Someone signed in on a till chooses their own PIN, proving the current one.
 * Only people on that till's staff list (ListTillStaff) can, and wrong
 * current PINs are limited per person so a till cannot be used to guess one.
 * Runs inside the device's tenant.
 */
final class ChangeOwnPin
{
    public const MAX_WRONG_PINS = 5;

    public const WRONG_PIN_DECAY_SECONDS = 900;

    public function handle(
        Device $device,
        string $staffId,
        #[SensitiveParameter] string $currentPin,
        #[SensitiveParameter] string $newPin,
    ): Staff {
        $limiterKey = 'pin-change:'.tenant('id').':'.$staffId;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_WRONG_PINS)) {
            throw new PinChangeRefused(PinChangeRefused::TOO_MANY_TRIES);
        }

        return DB::transaction(function () use ($device, $staffId, $currentPin, $newPin, $limiterKey) {
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':tenancy.staff'))");

            $staff = Staff::query()->find($staffId);

            if (! $staff instanceof Staff || ! $this->onTillStaffList($staff, $device)) {
                throw new PinChangeRefused(PinChangeRefused::NOT_FOUND);
            }

            if (! Hash::check($currentPin, (string) $staff->pin_hash)) {
                RateLimiter::hit($limiterKey, self::WRONG_PIN_DECAY_SECONDS);

                throw new PinChangeRefused(PinChangeRefused::WRONG_PIN);
            }

            $this->ensureAcceptable($staff, $currentPin, $newPin);

            $staff->forceFill(['pin_hash' => Hash::make($newPin), 'pin_set_at' => now()])->save();
            StaffChange::create([
                'staff_id' => $staff->id,
                'changed_by' => $staff->id,
                'action' => StaffChange::ACTION_PIN_CHANGED,
                'details' => ['device_id' => $device->id],
            ]);

            RateLimiter::clear($limiterKey);

            return $staff;
        });
    }

    /** The same people GET /api/v1/staff gives this till. */
    private function onTillStaffList(Staff $staff, Device $device): bool
    {
        $role = $staff->roleName();

        if (! $staff->is_active || $staff->pin_hash === null || $role === null) {
            return false;
        }

        return $role === 'owner' || $staff->branches()->whereKey($device->branch_id)->exists();
    }

    private function ensureAcceptable(Staff $staff, #[SensitiveParameter] string $currentPin, #[SensitiveParameter] string $newPin): void
    {
        if (strlen($newPin) !== Staff::pinLengthFor((string) $staff->roleName()) || ! ctype_digit($newPin)) {
            throw new PinChangeRefused(PinChangeRefused::WRONG_LENGTH);
        }

        if (ResetStaffPin::isObvious($newPin)) {
            throw new PinChangeRefused(PinChangeRefused::TOO_EASY);
        }

        if (hash_equals($currentPin, $newPin)) {
            throw new PinChangeRefused(PinChangeRefused::SAME_PIN);
        }
    }
}
