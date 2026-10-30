<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An open order at a table or the counter (table service), keyed by the
 * till's UUID. Lines are only ever added; paying it records an ordinary sale.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $device_id
 * @property string $staff_id
 * @property string|null $dining_table_id
 * @property string|null $label
 * @property string $status
 * @property Carbon $opened_at
 * @property Carbon|null $bill_requested_at
 * @property Carbon|null $closed_at
 * @property string|null $order_id
 * @property string|null $held_for_order_id the sale a till is taking payment with
 * @property string|null $held_by_device_id
 * @property Carbon|null $held_until
 */
class Ticket extends Model
{
    public const string OPEN = 'open';

    public const string BILL_REQUESTED = 'bill_requested';

    public const string CLOSED = 'closed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'bill_requested_at' => 'datetime',
            'closed_at' => 'datetime',
            'held_until' => 'datetime',
        ];
    }

    /** @return HasMany<TicketLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TicketLine::class)->orderBy('sent_at')->orderBy('created_at');
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }
}
