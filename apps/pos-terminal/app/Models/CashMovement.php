<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Enums\CashMovementKind;

/**
 * Cash that left this till's drawer in a shift other than change: a pay-out,
 * a drop to the safe or a void refund (VendaPOS\Core\Data\CashMovementData).
 *
 * @property string $id
 * @property string $shift_id
 * @property CashMovementKind $kind
 * @property int $amount_cents
 * @property Carbon $moved_at
 * @property string $staff_id
 * @property string|null $approved_by
 * @property string|null $reason
 * @property string|null $order_id the voided sale a refund was for
 * @property bool $synced
 */
#[Table(name: 'cash_movements', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class CashMovement extends Model
{
    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function toData(): CashMovementData
    {
        return CashMovementData::from($this->toSyncPayload());
    }

    /** @return array<string, mixed> */
    public function toSyncPayload(): array
    {
        return array_filter([
            'uuid' => $this->id,
            'kind' => $this->kind->value,
            'amount_cents' => $this->amount_cents,
            'moved_at' => $this->moved_at->toIso8601String(),
            'staff_id' => $this->staff_id,
            'approved_by' => $this->approved_by,
            'reason' => $this->reason,
            'order_uuid' => $this->order_id,
        ], fn (mixed $value) => $value !== null);
    }

    protected function casts(): array
    {
        return [
            'kind' => CashMovementKind::class,
            'amount_cents' => 'integer',
            'moved_at' => 'datetime',
            'synced' => 'boolean',
        ];
    }
}
