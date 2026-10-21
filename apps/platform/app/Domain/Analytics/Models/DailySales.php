<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One branch's sales for one Africa/Nairobi business day, net of voids.
 *
 * @property int $id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property int $order_count
 * @property int $void_count
 * @property int $subtotal_cents
 * @property int $vat_cents
 * @property int $total_cents
 */
class DailySales extends Model
{
    protected $table = 'daily_sales';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'order_count' => 'integer',
            'void_count' => 'integer',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
