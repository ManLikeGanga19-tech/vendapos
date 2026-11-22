<?php

declare(strict_types=1);

namespace App\CashUp;

use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Shift;
use App\Models\SyncState;
use VendaPOS\Core\CashUp\ShiftCash;
use VendaPOS\Core\ValueObjects\Money;

/**
 * This till's cash drawer: the shift open on it, if any, and what that shift
 * should hold, worked out with the same packages/core rule the server uses.
 */
final class Drawer
{
    /** How far a count may be off before closing needs a manager (from GET /device). */
    public const string ALLOWANCE = 'cash_up.variance_allowed_cents';

    /** The highest shift number the server has from this till (from GET /device). */
    public const string LAST_NUMBER = 'cash_up.last_shift_number';

    /** The server's default, used until the first sync says otherwise. */
    public const int DEFAULT_ALLOWANCE_CENTS = 10_000;

    public function current(): ?Shift
    {
        return Shift::query()->whereNull('closed_at')->orderByDesc('number')->first();
    }

    public function isOpen(): bool
    {
        return Shift::query()->whereNull('closed_at')->exists();
    }

    /** One more than any shift this till or the server knows of, so a reinstall never reuses a number. */
    public function nextNumber(): int
    {
        return max((int) Shift::query()->max('number'), (int) SyncState::read(self::LAST_NUMBER)) + 1;
    }

    public function allowance(): Money
    {
        $stored = SyncState::read(self::ALLOWANCE);

        return Money::fromCents($stored !== null && ctype_digit($stored) ? (int) $stored : self::DEFAULT_ALLOWANCE_CENTS);
    }

    /** Float, cash taken and cash out for $shift, with the expected cash it adds up to. */
    public function cash(Shift $shift): ShiftCash
    {
        $tenders = [];

        foreach (Order::query()->where('shift_id', $shift->id)->with('tenders')->get() as $order) {
            array_push($tenders, ...$order->tenderData());
        }

        return ShiftCash::for(
            $shift->openingFloat()->total(),
            $tenders,
            array_values($shift->movements()->get()->map(fn (CashMovement $movement) => $movement->toData())->all()),
        );
    }
}
