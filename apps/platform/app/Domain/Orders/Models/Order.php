<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\EtimsStatus;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A sale, keyed by the terminal's UUID. Lives in the tenant's schema.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $device_id
 * @property string|null $staff_id who made the sale; null only for orders from before tills sent it
 * @property int $sequence_number
 * @property OrderStatus $status
 * @property Carbon $placed_at
 * @property PaymentType|null $payment_type
 * @property int $subtotal_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property EtimsStatus|null $etims_status
 * @property array<string, mixed>|null $etims_receipt
 */
class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_type' => PaymentType::class,
            'placed_at' => 'datetime',
            'sequence_number' => 'integer',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'etims_status' => EtimsStatus::class,
            'etims_receipt' => 'array',
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

    public function total(): Money
    {
        return Money::fromCents($this->total_cents);
    }
}
