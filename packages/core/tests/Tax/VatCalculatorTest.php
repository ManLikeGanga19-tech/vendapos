<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\Tax\VatCalculator;
use VendaPOS\Core\ValueObjects\Money;

it('extracts 16% VAT from VAT-inclusive amounts, rounding half up to the cent', function (int $gross, int $vat) {
    expect(VatCalculator::inclusiveVat(Money::fromCents($gross), VatCategory::Standard)->cents)->toBe($vat);
})->with([
    'KES 1,600.00' => [160_000, 22_069],   // 22,068.97 → 22,069
    'KES 250.00' => [25_000, 3_448],        // 3,448.28 → 3,448
    'KES 1.00' => [100, 14],                // 13.79 → 14
    '4 cents' => [4, 1],                    // 0.55 → 1
    '3 cents' => [3, 0],                    // 0.41 → 0
    'zero' => [0, 0],
]);

it('charges no VAT on zero-rated or exempt amounts', function (VatCategory $category) {
    expect(VatCalculator::inclusiveVat(Money::fromCents(160_000), $category)->isZero())->toBeTrue();
})->with([VatCategory::ZeroRated, VatCategory::Exempt]);

it('stays exact for large amounts', function () {
    // KES 10,000,000.00: VAT = 1,379,310.34
    expect(VatCalculator::inclusiveVat(Money::fromCents(1_000_000_000), VatCategory::Standard)->cents)->toBe(137_931_034);
});
