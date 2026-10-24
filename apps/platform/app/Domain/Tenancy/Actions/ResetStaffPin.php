<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffChange;
use App\Domain\Tenancy\Support\StaffRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * Gives a staff member a new random till PIN and returns it, to be shown
 * once to whoever reset it and handed over. Only a hash is stored. The
 * length follows the role (Staff::PIN_LENGTHS); PINs anyone would guess
 * first (0000, 1234, 9876…) are never issued. PINs need not be unique:
 * on the till a person picks their name, then types their PIN.
 */
final class ResetStaffPin
{
    public function __construct(private readonly StaffRules $rules) {}

    public function handle(Staff $actor, string $staffId): string
    {
        return DB::transaction(function () use ($actor, $staffId) {
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':tenancy.staff'))");

            $staff = Staff::query()->findOrFail($staffId);

            if (! $this->rules->canManage($actor, $staff)) {
                throw new StaffChangeRefused(StaffChangeRefused::NOT_YOURS);
            }

            if (! $staff->is_active) {
                throw new StaffChangeRefused(StaffChangeRefused::INACTIVE);
            }

            $pin = self::generate(Staff::pinLengthFor((string) $staff->roleName()));

            $staff->forceFill(['pin_hash' => Hash::make($pin), 'pin_set_at' => now()])->save();
            StaffChange::create(['staff_id' => $staff->id, 'changed_by' => $actor->id, 'action' => StaffChange::ACTION_PIN_RESET]);

            return $pin;
        });
    }

    public static function generate(int $length): string
    {
        do {
            $pin = '';
            for ($i = 0; $i < $length; $i++) {
                $pin .= (string) random_int(0, 9);
            }
        } while (self::isObvious($pin));

        return $pin;
    }

    /** One digit repeated, or a run up or down (0123, 9876, 890123 wraps too). */
    public static function isObvious(#[SensitiveParameter] string $pin): bool
    {
        $digits = array_map(intval(...), str_split($pin));
        $steps = [];
        for ($i = 1, $n = count($digits); $i < $n; $i++) {
            $steps[] = ($digits[$i] - $digits[$i - 1] + 10) % 10;
        }

        $unique = array_unique($steps);

        return count($unique) === 1 && in_array($steps[0], [0, 1, 9], true);
    }
}
