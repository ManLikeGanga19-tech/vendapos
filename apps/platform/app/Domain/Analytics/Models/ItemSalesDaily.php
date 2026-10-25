<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What one item sold in one branch on one Africa/Nairobi business day, net of voids.
 *
 * @property int $id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property string $item_id
 * @property int $quantity
 * @property int $total_cents
 * @property int $vat_cents
 */
class ItemSalesDaily extends Model
{
    protected $table = 'item_sales_daily';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'quantity' => 'integer',
            'total_cents' => 'integer',
            'vat_cents' => 'integer',
        ];
    }
}
