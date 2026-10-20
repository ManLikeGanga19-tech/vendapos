<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

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
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'vat_category' => ['required', 'string'],
            'line_total_cents' => ['required', 'integer', 'min:0'],
        ];
    }
}
