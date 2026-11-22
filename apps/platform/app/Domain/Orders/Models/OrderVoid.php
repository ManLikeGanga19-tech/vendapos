<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Who voided a sale, who approved it and why. Written once, with the void.
 * Lives in the tenant's schema.
 *
 * @property string $order_id
 * @property string $device_id the till the void was made on
 * @property Carbon $voided_at
 * @property string $voided_by
 * @property string $approved_by
 * @property VoidReason $reason
 * @property string|null $note
 * @property bool $approver_allowed false when the approver could not approve voids at this branch when it arrived
 * @property bool $same_business_day false when voided on a later business day than the sale
 * @property Carbon $created_at
 */
class OrderVoid extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'order_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'voided_at' => 'datetime',
            'reason' => VoidReason::class,
            'approver_allowed' => 'boolean',
            'same_business_day' => 'boolean',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
