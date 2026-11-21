<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\VatCategory;

/**
 * One sent line of a ticket, at the menu price when it was sent. Never
 * edited; taking some or all of it off is a manager void (TicketLineVoid).
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $item_id
 * @property string $name
 * @property int $quantity
 * @property int $unit_price_cents
 * @property VatCategory $vat_category
 * @property int $line_total_cents
 * @property string $staff_id
 * @property Carbon $sent_at
 */
class TicketLine extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
            'vat_category' => VatCategory::class,
            'sent_at' => 'datetime',
        ];
    }

    /** As a sale line, for $quantity of it (all of it by default). */
    public function toOrderLine(?int $quantity = null): OrderLineData
    {
        $quantity ??= $this->quantity;

        return new OrderLineData(
            item_id: $this->item_id,
            name: $this->name,
            quantity: $quantity,
            unit_price_cents: $this->unit_price_cents,
            vat_category: $this->vat_category,
            line_total_cents: $this->unit_price_cents * $quantity,
        );
    }
}
