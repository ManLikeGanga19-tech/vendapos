<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Actions;

use App\Domain\CashUp\Models\CashMovement;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Orders\Actions\ListShiftTenders;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\CashUp\ShiftCash;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The server's own count of what a closed shift's drawer should hold,
 * worked out from the synced sales and movements with the same rule the
 * till uses (ShiftCash), never taken from the till. Run when a shift
 * closes and again whenever one of its sales arrives late, so the
 * figures settle once every sale has synced. Safe to run any number of times.
 */
final class ReconcileShift
{
    public function __construct(private readonly ListShiftTenders $listShiftTenders) {}

    public function handle(string $shiftId): ?Shift
    {
        return DB::transaction(function () use ($shiftId): ?Shift {
            $shift = Shift::query()->whereKey($shiftId)->lockForUpdate()->first();

            if ($shift === null || ! $shift->isClosed()) {
                return null;
            }

            $cash = ShiftCash::for(
                Money::fromCents($shift->float_cents),
                $this->listShiftTenders->handle($shift->id),
                array_values($shift->movements()->get()->map(fn (CashMovement $movement) => $movement->toData())->all()),
            );
            $expected = $cash->expected();

            $shift->update([
                'cash_taken_cents' => $cash->cashTaken->cents,
                'pay_outs_cents' => $cash->payOuts->cents,
                'drops_cents' => $cash->drops->cents,
                'void_refunds_cents' => $cash->voidRefunds->cents,
                'expected_cents' => $expected->cents,
                'variance_cents' => $cash->variance(Money::fromCents((int) $shift->counted_cents))->cents,
                'reconciled_at' => now(),
            ]);

            return $shift;
        });
    }
}
