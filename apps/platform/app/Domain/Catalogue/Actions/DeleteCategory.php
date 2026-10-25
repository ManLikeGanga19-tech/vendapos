<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Removes a category. Its items stay on the menu without one, and are marked
 * changed so tills pick up the move on their next sync; the foreign key alone
 * would clear category_id without touching updated_at.
 */
final class DeleteCategory
{
    public function handle(string $id): void
    {
        DB::transaction(function () use ($id) {
            $category = Category::query()->lockForUpdate()->findOrFail($id);

            Item::query()->where('category_id', $id)->update(['category_id' => null, 'updated_at' => now()]);

            $category->delete();
        });
    }
}
