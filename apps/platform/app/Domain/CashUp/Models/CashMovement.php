<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Enums\CashMovementKind;

/**
 * Cash that left a drawer during a shift: a pay-out, a drop or a void
 * refund. Written once, never changed. Lives in the tenant's schema.
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
 * @property Carbon $created_at
 */
class CashMovement extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => CashMovementKind::class,
            'amount_cents' => 'integer',
            'moved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function toData(): CashMovementData
    {
        return new CashMovementData(
            uuid: $this->id,
            kind: $this->kind,
            amount_cents: $this->amount_cents,
            moved_at: $this->moved_at->toIso8601String(),
            staff_id: $this->staff_id,
            approved_by: $this->approved_by,
            reason: $this->reason,
            order_uuid: $this->order_id,
        );
    }
}
