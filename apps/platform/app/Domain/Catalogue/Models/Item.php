<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A sellable catalogue entry. Prices are VAT-inclusive, in KES cents.
 * Items are deactivated rather than deleted so past orders keep their link.
 *
 * @property string $id
 * @property string|null $category_id
 * @property ItemType $type
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property int $price_cents
 * @property VatCategory $vat_category
 * @property bool $is_active
 * @property array<string, mixed>|null $etims
 * @property Carbon $updated_at
 */
class Item extends Model
{
    use HasUuids;

    protected $fillable = ['category_id', 'type', 'name', 'sku', 'barcode', 'price_cents', 'vat_category', 'is_active', 'etims'];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'vat_category' => VatCategory::class,
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'etims' => 'array',
        ];
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @param Builder<Item> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
