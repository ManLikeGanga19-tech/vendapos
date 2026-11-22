<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A table in use at this branch, copied from the server.
 *
 * @property string $id
 * @property string $floor_area_id
 * @property string $name
 * @property int $seats
 * @property int $sort_order
 */
#[Table(name: 'dining_tables', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class DiningTable extends Model
{
    protected function casts(): array
    {
        return ['seats' => 'integer', 'sort_order' => 'integer'];
    }
}
