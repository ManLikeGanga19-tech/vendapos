<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * On-hand quantity of one item at one branch. May be negative: offline sales
 * are never refused for stock, and negative levels are flagged to the owner.
 *
 * @property int $id
 * @property string $branch_id
 * @property string $item_id
 * @property int $quantity
 */
class StockLevel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @param Builder<StockLevel> $query */
    public function scopeNegative(Builder $query): void
    {
        $query->where('quantity', '<', 0);
    }
}
