<?php

namespace App\Support;

/**
 * Display helpers for subscription prices held as integer KES cents.
 * Rounding happens here, at presentation, and nowhere else.
 */
final class Price
{
    /** "KES 3,499" — whole shillings, half-up. */
    public static function shillings(int $cents): string
    {
        return 'KES '.number_format(intdiv($cents + 50, 100));
    }

    /** Total billed for a year when paying annually for `$monthsCharged` months. */
    public static function annualTotal(int $monthlyCents, int $monthsCharged): int
    {
        return $monthlyCents * $monthsCharged;
    }

    /** What annual billing works out to per month, in cents (half-up). */
    public static function annualMonthlyEquivalent(int $monthlyCents, int $monthsCharged): int
    {
        return intdiv(self::annualTotal($monthlyCents, $monthsCharged) * 2 + 12, 24);
    }
}
