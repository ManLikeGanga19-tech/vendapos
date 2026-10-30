<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\OrderTotals;
use VendaPOS\Core\Tax\VatBand;
use VendaPOS\Core\Tax\VatBreakdown;

it('splits a sale by VAT category, standard first, and adds up to the order totals', function () {
    $lines = [
        line(10_000, 1, VatCategory::Exempt),
        line(185_000, 1),
        line(10_000, 2, VatCategory::ZeroRated),
        line(35_000, 2),
    ];

    $bands = VatBreakdown::fromLines($lines);
    $totals = OrderTotals::fromLines($lines);

    expect(array_map(fn (VatBand $b) => [$b->category, $b->gross->cents, $b->vat->cents, $b->net()->cents], $bands))->toBe([
        [VatCategory::Standard, 255_000, 35_172, 219_828],
        [VatCategory::ZeroRated, 20_000, 0, 20_000],
        [VatCategory::Exempt, 10_000, 0, 10_000],
    ])
        ->and(array_sum(array_map(fn (VatBand $b) => $b->vat->cents, $bands)))->toBe($totals->vat->cents)
        ->and(array_sum(array_map(fn (VatBand $b) => $b->gross->cents, $bands)))->toBe($totals->total->cents);
});

it('leaves out categories the sale does not have', function () {
    expect(array_map(fn (VatBand $b) => $b->category, VatBreakdown::fromLines([line(5_000, 1)])))->toBe([VatCategory::Standard])
        ->and(VatBreakdown::fromLines([]))->toBe([]);
});

it('sums VAT line by line, the same rounding as the totals', function () {
    expect(VatBreakdown::fromLines([line(4, 1), line(4, 1)])[0]->vat->cents)
        ->toBe(OrderTotals::fromLines([line(4, 1), line(4, 1)])->vat->cents);
});
