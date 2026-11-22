<?php

declare(strict_types=1);

namespace App\CashUp;

use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StaffMember;
use App\Till\Till;
use Carbon\CarbonImmutable;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\ValueObjects\Money;

/**
 * An X report (the open shift so far) or a Z report (a closed shift), for
 * the screen and the printer alike. Every figure comes from Drawer, so what
 * is printed is what the till sent the server.
 */
final readonly class ShiftReport
{
    /**
     * @param  array<int, int>  $openingCounts  by shillings
     * @param  array<int, int>|null  $closingCounts  by shillings
     * @param  list<array{kind: string, amount: Money, reason: ?string, by: string, approved_by: ?string, at: CarbonImmutable}>  $movements
     */
    public function __construct(
        public bool $final,
        public string $number,
        public CarbonImmutable $openedAt,
        public string $openedBy,
        public ?CarbonImmutable $closedAt,
        public ?string $closedBy,
        public Money $float,
        public Money $cashTaken,
        public Money $payOuts,
        public Money $drops,
        public Money $voidRefunds,
        public Money $expected,
        public ?Money $counted,
        public ?Money $variance,
        public Money $mpesaTaken,
        public int $sales,
        public int $voided,
        public ?string $approvedBy,
        public ?string $note,
        public array $openingCounts,
        public ?array $closingCounts,
        public array $movements,
    ) {}

    public static function for(Shift $shift, Drawer $drawer, Till $till): self
    {
        $names = StaffMember::query()->pluck('name', 'id')->all();
        $name = fn (?string $id): ?string => $id === null ? null : ($names[$id] ?? '—');
        $cash = $drawer->cash($shift);
        $orders = Order::query()->where('shift_id', $shift->id)->with('tenders')->get();
        $mpesa = Money::zero();

        foreach ($orders as $order) {
            foreach ($order->tenderData() as $tender) {
                $mpesa = $tender->method === TenderMethod::Cash ? $mpesa : $mpesa->add($tender->amount());
            }
        }

        $counted = $shift->closingCount()?->total();
        // A closed shift reports what was expected when it closed, as sent.
        $expected = $shift->expected_cents !== null ? Money::fromCents($shift->expected_cents) : $cash->expected();
        $tillCode = $till->identity()?->till_code;

        return new self(
            final: $shift->isClosed(),
            number: self::label($tillCode, $shift->number),
            openedAt: $shift->opened_at->toImmutable(),
            openedBy: (string) $name($shift->opened_by),
            closedAt: $shift->closed_at?->toImmutable(),
            closedBy: $name($shift->closed_by),
            float: $cash->float,
            cashTaken: $cash->cashTaken,
            payOuts: $cash->payOuts,
            drops: $cash->drops,
            voidRefunds: $cash->voidRefunds,
            expected: $expected,
            counted: $counted,
            variance: $counted?->subtract($expected),
            mpesaTaken: $mpesa,
            sales: $orders->count(),
            voided: $orders->where('status', OrderStatus::Voided->value)->count(),
            approvedBy: $name($shift->approved_by),
            note: $shift->note,
            openingCounts: self::byDenomination($shift->opening_counts),
            closingCounts: $shift->closing_counts === null ? null : self::byDenomination($shift->closing_counts),
            movements: array_values($shift->movements()->get()->map(fn (CashMovement $movement) => [
                'kind' => $movement->kind->value,
                'amount' => Money::fromCents($movement->amount_cents),
                'reason' => $movement->reason,
                'by' => (string) $name($movement->staff_id),
                'approved_by' => $name($movement->approved_by),
                'at' => $movement->moved_at->toImmutable(),
            ])->all()),
        );
    }

    /** "T2-Z0007", or "Z0007" before the server has given the till a code. */
    public static function label(?string $tillCode, int $number): string
    {
        $z = 'Z'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);

        return $tillCode === null ? $z : "{$tillCode}-{$z}";
    }

    /**
     * @param  array<int|string, int>  $counts
     * @return array<int, int> largest first, without zeros
     */
    private static function byDenomination(array $counts): array
    {
        $sorted = [];

        foreach ($counts as $shillings => $count) {
            if ($count > 0) {
                $sorted[(int) $shillings] = $count;
            }
        }

        krsort($sorted);

        return $sorted;
    }
}
