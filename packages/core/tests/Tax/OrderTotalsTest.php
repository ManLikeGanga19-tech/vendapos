<?php

declare(strict_types=1);

use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\OrderTotals;

function line(int $unitPriceCents, int $quantity, VatCategory $category = VatCategory::Standard, ?int $lineTotal = null): OrderLineData
{
    return new OrderLineData(
        item_id: fake()->uuid(),
        name: 'Item',
        quantity: $quantity,
        unit_price_cents: $unitPriceCents,
        vat_category: $category,
        line_total_cents: $lineTotal ?? $unitPriceCents * $quantity,
    );
}

it('computes totals from VAT-inclusive lines', function () {
    // Nyama choma 1,850 + Tusker 2 × 350 standard-rated, plus a zero-rated KES 100 item.
    $totals = OrderTotals::fromLines([
        line(185_000, 1),
        line(35_000, 2),
        line(10_000, 1, VatCategory::ZeroRated),
    ]);

    // VAT: 25,517.24 → 25,517 and 9,655.17 → 9,655; zero-rated adds none.
    expect($totals->total->cents)->toBe(265_000)
        ->and($totals->vat->cents)->toBe(35_172)
        ->and($totals->subtotal->cents)->toBe(229_828)
        ->and(array_map(fn ($m) => $m->cents, $totals->lineVat))->toBe([25_517, 9_655, 0]);
});

it('sums VAT per line rather than taking it from the order total', function () {
    // Two 4-cent lines: 1 + 1 = 2 cents of VAT, where VAT on the 8-cent total would be 1.
    expect(OrderTotals::fromLines([line(4, 1), line(4, 1)])->vat->cents)->toBe(2);
});

it('reports no mismatches when the terminal’s figures are right', function () {
    $lines = [line(185_000, 1), line(35_000, 2)];

    expect(OrderTotals::mismatches($lines, 219_828, 35_172, 255_000))->toBe([]);
});

it('reports every figure that disagrees', function () {
    $lines = [line(35_000, 2, lineTotal: 69_000), line(185_000, 1)];

    expect(array_keys(OrderTotals::mismatches($lines, 1, 2, 3)))
        ->toBe(['lines.0.line_total_cents', 'subtotal_cents', 'vat_cents', 'total_cents']);
});
