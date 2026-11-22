<?php

declare(strict_types=1);

namespace VendaPOS\Core\Tax;

use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/** The lines of one VAT category in a sale: what they cost (VAT included) and the VAT in it. */
final readonly class VatBand
{
    public function __construct(
        public VatCategory $category,
        public Money $gross,
        public Money $vat,
    ) {}

    public function net(): Money
    {
        return $this->gross->subtract($this->vat);
    }
}
