<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property int $sort_order
 */
class Category extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'sort_order'];

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
