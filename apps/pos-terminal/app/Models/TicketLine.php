<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Data\OrderLineData;

/**
 * One sent line of a ticket, locked once sent: its quantity and price never
 * change. Taking some or all of it off is a manager void (TicketLineVoid).
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $item_id
 * @property string $name
 * @property int $quantity
 * @property int $unit_price_cents
 * @property string $vat_category
 * @property string $staff_id
 * @property Carbon $sent_at
 * @property string $sync_status
 */
#[Table(name: 'ticket_lines', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class TicketLine extends Model
{
    public const string PENDING = 'pending';

    public const string SYNCED = 'synced';

    /** Sent after the ticket was paid: not paid for. */
    public const string REFUSED = 'refused';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return HasMany<TicketLineVoid, $this> */
    public function voids(): HasMany
    {
        return $this->hasMany(TicketLineVoid::class);
    }

    /** How many were taken off; a void the server refused takes nothing off. */
    public function voidedQuantity(): int
    {
        return (int) $this->voids->where('sync_status', '!=', TicketLineVoid::REFUSED)->sum('quantity');
    }

    /** What is left to pay for. */
    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->voidedQuantity());
    }

    /** As a sale line, for what is left of it. */
    public function toOrderLine(): OrderLineData
    {
        $quantity = $this->remainingQuantity();

        return OrderLineData::from([
            'item_id' => $this->item_id,
            'name' => $this->name,
            'quantity' => $quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'vat_category' => $this->vat_category,
            'line_total_cents' => $this->unit_price_cents * $quantity,
        ]);
    }

    /**
     * The line as POST /api/v1/tickets/sync takes it (VendaPOS\Core\Data\TicketLineData).
     *
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array
    {
        return [
            'uuid' => $this->id,
            'item_id' => $this->item_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'vat_category' => $this->vat_category,
            'staff_id' => $this->staff_id,
            'sent_at' => $this->sent_at->toIso8601String(),
        ];
    }
}
