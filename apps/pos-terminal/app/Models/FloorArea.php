<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A part of this branch's floor (Main hall, Terrace), copied from the server.
 *
 * @property string $id
 * @property string $name
 * @property int $sort_order
 */
#[Table(name: 'floor_areas', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class FloorArea extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort_order')->orderBy('name');
    }
}
