<?php

declare(strict_types=1);

namespace App\CashUp;

use App\Jobs\PushOrdersJob;
use App\Models\Shift;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\CashCountData;

/**
 * Opens a drawer shift with the float counted note by note. Anyone who takes
 * payments may open one; nothing is sold on this till until someone has.
 */
final class OpenShift
{
    public function __construct(private readonly Drawer $drawer) {}

    /** @throws CashUpRefused */
    public function handle(StaffMember $staff, CashCountData $float): Shift
    {
        if (! $staff->can(StaffMember::TAKE_PAYMENTS)) {
            throw new CashUpRefused('not_allowed');
        }

        $shift = DB::transaction(function () use ($staff, $float): Shift {
            if ($this->drawer->isOpen()) {
                throw new CashUpRefused('already_open');
            }

            return Shift::query()->create([
                'id' => (string) Str::uuid7(),
                'number' => $this->drawer->nextNumber(),
                'opened_at' => now()->utc(),
                'opened_by' => $staff->id,
                'opening_counts' => $float->counts,
                'sync_status' => Shift::PENDING,
            ]);
        });

        // Up to the server within seconds when online, ahead of the shift's sales.
        PushOrdersJob::dispatch();

        return $shift;
    }
}
