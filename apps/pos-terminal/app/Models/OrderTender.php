<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * One way part of a local sale was paid (VendaPOS\Core\Data\TenderData).
 *
 * @property int $id
 * @property string $order_id
 * @property int $position
 * @property string $method
 * @property int $amount_cents
 * @property int|null $tendered_cents
 * @property int|null $change_cents
 * @property string|null $payment_uuid
 * @property string|null $mpesa_reference
 */
#[Table(name: 'order_tenders', timestamps: false)]
#[Unguarded]
final class OrderTender extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'amount_cents' => 'integer',
            'tendered_cents' => 'integer',
            'change_cents' => 'integer',
        ];
    }
}
