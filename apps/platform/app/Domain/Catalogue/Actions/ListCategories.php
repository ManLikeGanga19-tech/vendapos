<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Category;

/** The menu's categories in till order, each with how many active items it holds. */
final class ListCategories
{
    /** @return list<array{id: string, name: string, sort_order: int, item_count: int}> */
    public function handle(): array
    {
        return array_values(Category::query()
            ->withCount(['items' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'item_count' => (int) $category->getAttribute('items_count'),
            ])
            ->all());
    }
}
