<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\VatCategory;

/** One line of an eTIMS document, as sold. Amounts are VAT-inclusive KES cents. */
final class EtimsLineData extends Data
{
    /**
     * @param  array<string, mixed>|null  $item_codes  the item's eTIMS codes from the catalogue (classification, units); null until set
     */
    public function __construct(
        public string $item_id,
        public string $name,
        public int $quantity,
        public int $unit_price_cents,
        public VatCategory $vat_category,
        public int $line_total_cents,
        public int $vat_cents,
        public ?array $item_codes,
    ) {}
}
