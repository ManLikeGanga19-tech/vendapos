<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/** One line of a sale. Prices are VAT-inclusive, in KES cents. */
final class OrderLineData extends Data
{
    public function __construct(
        public string $item_id,
        public string $name,
        public int $quantity,
        public int $unit_price_cents,
        public VatCategory $vat_category,
        public int $line_total_cents,
    ) {}

    public function unitPrice(): Money
    {
        return Money::fromCents($this->unit_price_cents);
    }

    public function lineTotal(): Money
    {
        return Money::fromCents($this->line_total_cents);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'item_id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'vat_category' => ['required', Rule::enum(VatCategory::class)],
            'line_total_cents' => ['required', 'integer', 'min:0'],
        ];
    }
}
