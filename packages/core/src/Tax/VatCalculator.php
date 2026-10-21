<?php

declare(strict_types=1);

namespace VendaPOS\Core\Tax;

use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/**
 * VAT for VAT-inclusive prices, in integer cents (approved by Daniel, M1).
 * The VAT inside a gross amount is gross × rate ÷ (1 + rate), rounded half
 * up to the cent; it is computed per line and summed, never on a total.
 */
final class VatCalculator
{
    public static function inclusiveVat(Money $gross, VatCategory $category): Money
    {
        $rate = $category->rate(); // basis points: 1600 = 16%

        if ($rate === 0 || $gross->isZero()) {
            return Money::zero();
        }

        $divisor = 10_000 + $rate;

        // Half-up rounding in integer arithmetic: floor((2·g·r + d) / 2d).
        return Money::fromCents(intdiv(2 * $gross->cents * $rate + $divisor, 2 * $divisor));
    }
}
