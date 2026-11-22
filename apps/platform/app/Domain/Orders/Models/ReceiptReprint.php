<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A receipt printed again at a till. Append-only. Lives in the tenant's schema.
 *
 * @property string $id
 * @property string $order_id
 * @property string $device_id
 * @property string $staff_id
 * @property Carbon $reprinted_at
 * @property Carbon $created_at
 */
class ReceiptReprint extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reprinted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
