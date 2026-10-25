<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VendaPOS\Core\Enums\TenderMethod;

/**
 * One way part of a sale was paid. Lives in the tenant's schema.
 *
 * @property int $id
 * @property string $order_id
 * @property int $position
 * @property TenderMethod $method
 * @property int $amount_cents
 * @property int|null $tendered_cents
 * @property int|null $change_cents
 * @property string|null $payment_id
 * @property string|null $mpesa_reference
 */
class OrderTender extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'method' => TenderMethod::class,
            'position' => 'integer',
            'amount_cents' => 'integer',
            'tendered_cents' => 'integer',
            'change_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
