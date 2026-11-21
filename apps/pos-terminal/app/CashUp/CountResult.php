<?php

declare(strict_types=1);

namespace App\CashUp;

use VendaPOS\Core\ValueObjects\Money;

/** A closing count compared with what the drawer should hold. */
final readonly class CountResult
{
    public function __construct(
        public Money $expected,
        public Money $counted,
        // Counted minus expected: below zero short, above zero over.
        public Money $variance,
        public bool $needsApproval,
    ) {}
}
