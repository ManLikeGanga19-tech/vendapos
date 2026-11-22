<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Some or all of a sent ticket line, taken off before payment with a
 * manager's or owner's PIN (T5c). Never edited or removed.
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $ticket_line_id
 * @property string $device_id
 * @property int $quantity
 * @property Carbon $voided_at
 * @property string $voided_by
 * @property string $approved_by
 * @property VoidReason $reason
 * @property string|null $note
 * @property bool $approver_allowed
 * @property Carbon $created_at
 */
class TicketLineVoid extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'voided_at' => 'datetime',
            'reason' => VoidReason::class,
            'approver_allowed' => 'boolean',
        ];
    }
}
