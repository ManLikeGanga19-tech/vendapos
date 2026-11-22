<?php

declare(strict_types=1);

namespace VendaPOS\Core\CashUp;

use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The cash a drawer should hold at the end of a shift, worked out the same
 * way on the till and on the server:
 *
 *   float + cash taken − pay-outs − drops − void refunds
 *
 * "Cash taken" is what cash tenders paid towards sales (handed over minus
 * change), including sales voided later: their cash came in, and the refund
 * that went back out is its own movement. M-Pesa is not in the drawer.
 */
final readonly class ShiftCash
{
    private function __construct(
        public Money $float,
        public Money $cashTaken,
        public Money $payOuts,
        public Money $drops,
        public Money $voidRefunds,
    ) {}

    /**
     * @param  list<TenderData>  $tenders  every tender of every sale in the shift
     * @param  list<CashMovementData>  $movements  the cash that left the drawer in the shift
     */
    public static function for(Money $float, array $tenders, array $movements): self
    {
        $cash = Money::zero();

        foreach ($tenders as $tender) {
            if ($tender->method === TenderMethod::Cash) {
                $cash = $cash->add($tender->amount());
            }
        }

        $out = fn (CashMovementKind $kind): Money => array_reduce(
            array_filter($movements, fn (CashMovementData $movement) => $movement->kind === $kind),
            fn (Money $sum, CashMovementData $movement) => $sum->add($movement->amount()),
            Money::zero(),
        );

        return new self($float, $cash, $out(CashMovementKind::PayOut), $out(CashMovementKind::Drop), $out(CashMovementKind::VoidRefund));
    }

    /** Can be negative when more left the drawer than came in; that is flagged, not hidden. */
    public function expected(): Money
    {
        return $this->float->add($this->cashTaken)
            ->subtract($this->payOuts)
            ->subtract($this->drops)
            ->subtract($this->voidRefunds);
    }

    /** Counted minus expected: below zero the drawer is short, above zero it is over. */
    public function variance(Money $counted): Money
    {
        return $counted->subtract($this->expected());
    }

    /** Short or over by more than the business allows: a manager's PIN and a note are needed to close. */
    public function needsApproval(Money $counted, Money $allowed): bool
    {
        return abs($this->variance($counted)->cents) > $allowed->cents;
    }
}
