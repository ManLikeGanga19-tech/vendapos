<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use VendaPOS\Core\Enums\ItemType;

/**
 * One page of the menu for the Owner panel, by name, with a total count.
 * Only the types the menu screens manage (SaveMenuItem::TYPES).
 * Searches name, SKU and barcode in the database, with % and _ taken
 * literally, so a typed "50%" finds "50% juice" and nothing else.
 */
final class ListMenuItems
{
    public const ON_SALE = 'on_sale';

    public const ARCHIVED = 'archived';

    /** Filters by category: no category at all. */
    public const NO_CATEGORY = 'none';

    /**
     * @return array{total: int, rows: list<array{id: string, name: string, type: string, category_id: string|null, category: string|null, price_cents: int, vat_category: string, sku: string|null, barcode: string|null, on_sale: bool}>}
     */
    public function handle(?string $search = null, ?string $categoryId = null, string $status = self::ON_SALE, int $page = 1, int $perPage = 25): array
    {
        $query = Item::query()
            ->with('category:id,name')
            ->whereIn('type', array_map(fn (ItemType $type) => $type->value, SaveMenuItem::TYPES))
            ->where('is_active', $status !== self::ARCHIVED)
            ->when($categoryId === self::NO_CATEGORY, fn (Builder $query) => $query->whereNull('category_id'))
            ->when($categoryId !== null && $categoryId !== self::NO_CATEGORY, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when(filled($search), function (Builder $query) use ($search) {
                $like = '%'.addcslashes(trim((string) $search), '\\%_').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'ilike', $like)
                    ->orWhere('sku', 'ilike', $like)
                    ->orWhere('barcode', 'ilike', $like));
            });

        $total = (clone $query)->count();

        $rows = array_values($query
            ->orderBy('name')
            ->orderBy('id')
            ->forPage(max(1, $page), max(1, $perPage))
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type->value,
                'category_id' => $item->category_id,
                'category' => $item->category?->name,
                'price_cents' => $item->price_cents,
                'vat_category' => $item->vat_category->value,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'on_sale' => $item->is_active,
            ])
            ->all());

        return ['total' => $total, 'rows' => $rows];
    }
}
