<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Actions;

use App\Domain\CashUp\Models\CashCheck;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Orders\Actions\ListSalesNamingShifts;

/**
 * The cash-up problems only time reveals, run inside one tenant: a shift
 * still open long after it opened (a till left running, lost or broken),
 * and sales naming a shift the server never received.
 */
final class FlagStaleShifts
{
    public function __construct(private readonly ListSalesNamingShifts $listSalesNamingShifts) {}

    public function handle(): void
    {
        $openHours = (int) config('vendapos.cash_up.open_too_long_hours');

        Shift::query()->whereNull('closed_at')->where('opened_at', '<', now()->subHours($openHours))->each(function (Shift $shift) {
            CashCheck::open(CashCheck::OPEN_TOO_LONG, "shift:{$shift->id}", [
                'branch_id' => $shift->branch_id,
                'shift_id' => $shift->id,
                'staff_id' => $shift->opened_by,
                'business_date' => $shift->opened_at->copy()->setTimezone((string) config('vendapos.business_timezone'))->toDateString(),
                'details' => ['number' => $shift->number, 'device_id' => $shift->device_id, 'hours' => (int) $shift->opened_at->diffInHours(now())],
            ]);
        });

        // A till sends its shifts before its sales, so a shift still missing after the
        // grace period is lost, not late.
        $grace = (int) config('vendapos.cash_up.missing_shift_grace_minutes');
        $sales = $this->listSalesNamingShifts->handle(now()->subDays(7), now()->subMinutes($grace));
        $known = Shift::query()->whereKey(array_unique(array_column($sales, 'shift_id')))->pluck('id')->all();

        foreach ($sales as $sale) {
            if (! in_array($sale['shift_id'], $known, true)) {
                CashCheck::open(CashCheck::SALE_OUTSIDE_SHIFT, "order:{$sale['order_id']}", [
                    'branch_id' => $sale['branch_id'],
                    'order_id' => $sale['order_id'],
                    'shift_id' => $sale['shift_id'],
                    'staff_id' => $sale['staff_id'],
                    'amount_cents' => $sale['total_cents'],
                    'business_date' => $sale['placed_at']->copy()->setTimezone((string) config('vendapos.business_timezone'))->toDateString(),
                    'details' => ['receipt_number' => $sale['receipt_number'], 'device_id' => $sale['device_id'], 'missing_shift' => true],
                ]);
            }
        }
    }
}
