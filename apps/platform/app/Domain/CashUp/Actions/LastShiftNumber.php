<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Actions;

use App\Domain\CashUp\Models\Shift;

/**
 * The highest shift number the server has from one till, 0 for none. A till
 * reinstalled with an empty database carries on from here, so its next Z
 * number never collides with one already synced.
 */
final class LastShiftNumber
{
    public function handle(string $deviceId): int
    {
        return (int) Shift::query()->where('device_id', $deviceId)->max('number');
    }
}
