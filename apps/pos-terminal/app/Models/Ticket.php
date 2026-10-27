<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Tax\OrderTotals;

/**
 * An open order at a table or the counter (table service). Opened on any till
 * of the branch; this till's copy is written here first and synced after.
 *
 * @property string $id
 * @property string|null $table_id
 * @property string|null $table_name
 * @property string|null $label
 * @property string $staff_id
 * @property string $status
 * @property Carbon $opened_at
 * @property Carbon|null $bill_requested_at
 * @property string|null $order_id
 * @property string $sync_status
 * @property string|null $sync_reason
 */
#[Table(name: 'tickets', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class Ticket extends Model
{
    public const string OPEN = 'open';

    public const string BILL_REQUESTED = 'bill_requested';

    public const string CLOSED = 'closed';

    public const string PENDING = 'pending';

    public const string SYNCED = 'synced';

    public const string REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'bill_requested_at' => 'datetime',
        ];
    }

    /** @return HasMany<TicketLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TicketLine::class)->orderBy('sent_at')->orderBy('id');
    }

    /** @param Builder<self> $query */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', '!=', self::CLOSED)->orderBy('opened_at');
    }

    /** What the guest sees: the table, or the takeaway name. */
    public function title(): string
    {
        return match (true) {
            $this->table_name !== null => __('terminal.tickets.table', ['name' => $this->table_name]),
            $this->label !== null && $this->label !== '' => $this->label,
            default => __('terminal.tickets.takeaway'),
        };
    }

    /**
     * Lines the bill is for: everything sent, except lines refused because they came after payment.
     *
     * @return list<TicketLine>
     */
    public function billedLines(): array
    {
        return array_values($this->lines->where('sync_status', '!=', TicketLine::REFUSED)->all());
    }

    public function totals(): OrderTotals
    {
        return OrderTotals::fromLines(array_map(fn (TicketLine $line) => $line->toOrderLine(), $this->billedLines()));
    }

    /**
     * The ticket as POST /api/v1/tickets/sync takes it, with only the lines the
     * server does not have yet (VendaPOS\Core\Data\TicketData).
     *
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array
    {
        return [
            'uuid' => $this->id,
            'staff_id' => $this->staff_id,
            'opened_at' => $this->opened_at->toIso8601String(),
            'table_id' => $this->table_id,
            'label' => $this->label,
            'lines' => $this->lines->where('sync_status', TicketLine::PENDING)->map(fn (TicketLine $line) => $line->toSyncPayload())->values()->all(),
            'bill_requested_at' => $this->bill_requested_at?->toIso8601String(),
        ];
    }
}
