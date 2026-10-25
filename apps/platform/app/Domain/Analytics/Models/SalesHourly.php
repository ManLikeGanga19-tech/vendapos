<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One branch's sales for one hour of an Africa/Nairobi business day, net of voids.
 *
 * @property int $id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property int $hour
 * @property int $order_count
 * @property int $void_count
 * @property int $cancelled_count
 * @property int $subtotal_cents
 * @property int $vat_cents
 * @property int $total_cents
 */
class SalesHourly extends Model
{
    protected $table = 'sales_hourly';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'hour' => 'integer',
            'order_count' => 'integer',
            'void_count' => 'integer',
            'cancelled_count' => 'integer',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
