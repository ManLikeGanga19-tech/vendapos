<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A receipt printed again on this till, kept until the server has it for the
 * owner's audit.
 *
 * @property string $id
 * @property string $order_id
 * @property string $staff_id
 * @property Carbon $reprinted_at
 * @property string $sync_status
 * @property string|null $sync_reason
 * @property Carbon|null $synced_at
 */
#[Table(name: 'receipt_reprints', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class ReceiptReprint extends Model
{
    protected function casts(): array
    {
        return [
            'reprinted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Pending reprints of sales the server already has: it refuses a reprint
     * of a sale it has not seen, so the rest wait for their sale.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReadyToSync(Builder $query): void
    {
        $query->where('sync_status', Order::PENDING)
            ->whereHas('order', fn (Builder $order) => $order->where('sync_status', Order::SYNCED))
            ->orderBy('reprinted_at');
    }

    /**
     * As POST /api/v1/receipts/reprints/sync takes it (VendaPOS\Core\Data\ReceiptReprintData).
     *
     * @return array<string, string>
     */
    public function toSyncPayload(): array
    {
        return [
            'uuid' => $this->id,
            'order_uuid' => $this->order_id,
            'staff_id' => $this->staff_id,
            'reprinted_at' => $this->reprinted_at->toIso8601String(),
        ];
    }
}
