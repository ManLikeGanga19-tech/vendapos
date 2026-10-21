<?php

declare(strict_types=1);

namespace VendaPOS\Core\Tax;

use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The authoritative totals of an order, computed from its lines exactly as
 * the terminal must compute them: line total = unit price × quantity, VAT per
 * line from VatCalculator, then summed. The server recomputes these and
 * rejects orders whose stated totals differ (approved by Daniel, M1).
 */
final readonly class OrderTotals
{
    /** @param list<Money> $lineVat VAT of each line, in line order */
    private function __construct(
        public Money $subtotal,
        public Money $vat,
        public Money $total,
        public array $lineVat,
    ) {}

    /** @param iterable<OrderLineData> $lines */
    public static function fromLines(iterable $lines): self
    {
        $total = Money::zero();
        $vat = Money::zero();
        $lineVat = [];

        foreach ($lines as $line) {
            $lineTotal = $line->unitPrice()->multiply($line->quantity);
            $lineTax = VatCalculator::inclusiveVat($lineTotal, $line->vat_category);

            $total = $total->add($lineTotal);
            $vat = $vat->add($lineTax);
            $lineVat[] = $lineTax;
        }

        return new self($total->subtract($vat), $vat, $total, $lineVat);
    }

    /**
     * Every way the stated figures disagree with the computed ones, as
     * field => message. Empty when they all match.
     *
     * @param  iterable<OrderLineData>  $lines
     * @return array<string, string>
     */
    public static function mismatches(iterable $lines, int $subtotalCents, int $vatCents, int $totalCents): array
    {
        $errors = [];
        $index = 0;

        foreach ($lines as $line) {
            $expected = $line->unit_price_cents * $line->quantity;

            if ($line->line_total_cents !== $expected) {
                $errors["lines.{$index}.line_total_cents"] = "Expected {$expected}, got {$line->line_total_cents}.";
            }

            $index++;
        }

        $computed = self::fromLines($lines);

        foreach (['subtotal' => $subtotalCents, 'vat' => $vatCents, 'total' => $totalCents] as $field => $stated) {
            if ($computed->{$field}->cents !== $stated) {
                $errors["{$field}_cents"] = "Expected {$computed->{$field}->cents}, got {$stated}.";
            }
        }

        return $errors;
    }
}
