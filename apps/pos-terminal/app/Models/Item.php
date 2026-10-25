<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A product or service, copied from the server's catalogue. Price in KES cents.
 *
 * @property string $id
 * @property string|null $category_id
 * @property string $type
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property int $price_cents
 * @property string $vat_category
 * @property bool $is_active
 */
#[Table(name: 'items', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class Item extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
