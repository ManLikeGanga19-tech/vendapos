<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A sale made on this till, kept here until the server has it (and after,
 * for the day's list). Amounts in KES cents, VAT-inclusive.
 *
 * @property string $id
 * @property int $sequence_number
 * @property string $staff_id
 * @property string $status
 * @property Carbon $placed_at
 * @property int $subtotal_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property string $sync_status
 * @property string|null $sync_reason
 * @property array<string, mixed>|null $sync_errors
 * @property Carbon|null $synced_at
 */
#[Table(name: 'orders', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class Order extends Model
{
    public const string PENDING = 'pending';

    public const string SYNCED = 'synced';

    public const string REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'placed_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'sync_errors' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('position');
    }

    /** @return HasMany<OrderTender, $this> */
    public function tenders(): HasMany
    {
        return $this->hasMany(OrderTender::class)->orderBy('position');
    }

    /** @param Builder<self> $query */
    public function scopeAwaitingSync(Builder $query): void
    {
        $query->where('sync_status', self::PENDING)->orderBy('sequence_number');
    }

    /**
     * The sale as POST /api/v1/orders/sync takes it (VendaPOS\Core\Data\OrderData).
     *
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array
    {
        return [
            'uuid' => $this->id,
            'sequence_number' => $this->sequence_number,
            'placed_at' => $this->placed_at->toIso8601String(),
            'staff_id' => $this->staff_id,
            'status' => $this->status,
            'lines' => $this->lines->map(fn (OrderLine $line) => [
                'item_id' => $line->item_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'vat_category' => $line->vat_category,
                'line_total_cents' => $line->line_total_cents,
            ])->values()->all(),
            // Only the fields each method uses: the server rejects the others.
            'tenders' => $this->tenders->map(fn (OrderTender $tender) => array_filter([
                'method' => $tender->method,
                'amount_cents' => $tender->amount_cents,
                'tendered_cents' => $tender->tendered_cents,
                'change_cents' => $tender->change_cents,
                'payment_uuid' => $tender->payment_uuid,
                'mpesa_reference' => $tender->mpesa_reference,
            ], fn (mixed $value) => $value !== null))->values()->all(),
            'subtotal_cents' => $this->subtotal_cents,
            'vat_cents' => $this->vat_cents,
            'total_cents' => $this->total_cents,
        ];
    }
}
