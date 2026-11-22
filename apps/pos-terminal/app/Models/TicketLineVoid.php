<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Some or all of a sent ticket line, taken off with a manager's or owner's
 * PIN before payment (T5c). Pending until the server has it; refused when
 * the server would not take it off (the ticket was paid, or being paid on
 * another till), and then the line counts in full again.
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $ticket_line_id
 * @property int $quantity
 * @property Carbon $voided_at
 * @property string $voided_by
 * @property string $approved_by
 * @property string $reason
 * @property string|null $note
 * @property string $sync_status
 */
#[Table(name: 'ticket_line_voids', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class TicketLineVoid extends Model
{
    public const string PENDING = 'pending';

    public const string SYNCED = 'synced';

    public const string REFUSED = 'refused';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * The void as POST /api/v1/tickets/sync takes it (VendaPOS\Core\Data\TicketLineVoidData).
     *
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array
    {
        return [
            'uuid' => $this->id,
            'line_uuid' => $this->ticket_line_id,
            'quantity' => $this->quantity,
            'voided_at' => $this->voided_at->toIso8601String(),
            'voided_by' => $this->voided_by,
            'approved_by' => $this->approved_by,
            'reason' => $this->reason,
            'note' => $this->note,
        ];
    }
}
