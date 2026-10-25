<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One signed change to a branch's stock; the ledger behind StockLevel.
 *
 * @property int $id
 * @property string $branch_id
 * @property string $item_id
 * @property int $quantity
 * @property string $reason
 * @property string|null $order_id
 * @property string $event_id
 * @property Carbon $created_at
 */
class StockMovement extends Model
{
    public const string REASON_SALE = 'sale';

    public const string REASON_VOID = 'void';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
