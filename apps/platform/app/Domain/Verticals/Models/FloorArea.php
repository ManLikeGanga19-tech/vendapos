<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A part of a branch's floor (Main hall, Terrace, Bar) that groups its
 * tables on the till. Table service pack only.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $name
 * @property int $sort_order
 */
class FloorArea extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
