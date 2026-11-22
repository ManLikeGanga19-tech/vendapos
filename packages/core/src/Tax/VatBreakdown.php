<?php

declare(strict_types=1);

namespace VendaPOS\Core\Tax;

use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A sale's VAT by category, for receipts and bills: standard, then
 * zero-rated, then exempt, each only when the sale has such lines. VAT is
 * taken per line exactly as OrderTotals does, so the bands add up to it.
 */
final class VatBreakdown
{
    /**
     * @param  iterable<OrderLineData>  $lines
     * @return list<VatBand>
     */
    public static function fromLines(iterable $lines): array
    {
        /** @var array<string, array{gross: Money, vat: Money}> $sums */
        $sums = [];

        foreach ($lines as $line) {
            $lineTotal = $line->unitPrice()->multiply($line->quantity);
            $key = $line->vat_category->value;
            $sums[$key] ??= ['gross' => Money::zero(), 'vat' => Money::zero()];
            $sums[$key]['gross'] = $sums[$key]['gross']->add($lineTotal);
            $sums[$key]['vat'] = $sums[$key]['vat']->add(VatCalculator::inclusiveVat($lineTotal, $line->vat_category));
        }

        $bands = [];

        foreach (VatCategory::cases() as $category) {
            if (isset($sums[$category->value])) {
                $bands[] = new VatBand($category, $sums[$category->value]['gross'], $sums[$category->value]['vat']);
            }
        }

        return $bands;
    }
}
