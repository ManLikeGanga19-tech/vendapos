<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Enums\VatCategory;

/**
 * @property int $id
 * @property string $order_id
 * @property int $position
 * @property string $item_id
 * @property string $name
 * @property int $quantity
 * @property int $unit_price_cents
 * @property VatCategory $vat_category
 * @property int $line_total_cents
 * @property int $vat_cents
 */
class OrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vat_category' => VatCategory::class,
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
            'vat_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function toOrderLineData(): OrderLineData
    {
        return new OrderLineData(
            item_id: $this->item_id,
            name: $this->name,
            quantity: $this->quantity,
            unit_price_cents: $this->unit_price_cents,
            vat_category: $this->vat_category,
            line_total_cents: $this->line_total_cents,
        );
    }
}
