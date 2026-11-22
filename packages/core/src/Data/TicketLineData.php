<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/**
 * One line a waiter sent on a ticket. Its price is the one on the menu when
 * it was sent and never changes after (Daniel, T4b). VAT-inclusive, KES cents.
 */
final class TicketLineData extends Data
{
    public function __construct(
        // Client-generated; resending the same line adds nothing.
        public string $uuid,
        public string $item_id,
        public string $name,
        public int $quantity,
        public int $unit_price_cents,
        public VatCategory $vat_category,
        // Who sent it: the waiter, or the cashier adding to a ticket.
        public string $staff_id,
        public string $sent_at,
    ) {}

    public function lineTotal(): Money
    {
        return Money::fromCents($this->unit_price_cents * $this->quantity);
    }

    /** The same line as a sale line, for totals and for paying the ticket. */
    public function toOrderLine(): OrderLineData
    {
        return new OrderLineData(
            item_id: $this->item_id,
            name: $this->name,
            quantity: $this->quantity,
            unit_price_cents: $this->unit_price_cents,
            vat_category: $this->vat_category,
            line_total_cents: $this->lineTotal()->cents,
        );
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'item_id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'vat_category' => ['required', Rule::enum(VatCategory::class)],
            'staff_id' => ['required', 'string', 'uuid'],
            'sent_at' => ['required', 'date'],
        ];
    }
}
