<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A table guests sit at, in one area of one branch. Switched off rather than
 * deleted, so past tickets keep pointing at it.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $floor_area_id
 * @property string $name
 * @property int $seats
 * @property bool $is_active
 * @property int $sort_order
 */
class DiningTable extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<FloorArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(FloorArea::class, 'floor_area_id');
    }
}
