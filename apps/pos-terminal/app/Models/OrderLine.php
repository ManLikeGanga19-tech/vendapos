<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use VendaPOS\Core\Data\OrderLineData;

/**
 * One line of a local sale, with the item's name, price and VAT as they were
 * when it was sold.
 *
 * @property int $id
 * @property string $order_id
 * @property int $position
 * @property string $item_id
 * @property string $name
 * @property int $quantity
 * @property int $unit_price_cents
 * @property string $vat_category
 * @property int $line_total_cents
 */
#[Table(name: 'order_lines', timestamps: false)]
#[Unguarded]
final class OrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    public function toOrderLine(): OrderLineData
    {
        return OrderLineData::from([
            'item_id' => $this->item_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'vat_category' => $this->vat_category,
            'line_total_cents' => $this->line_total_cents,
        ]);
    }
}
