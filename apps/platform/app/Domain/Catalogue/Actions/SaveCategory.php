<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Exceptions\MenuConflict;
use App\Domain\Catalogue\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Adds a category, or renames and reorders one. Names are unique ignoring
 * case and surrounding spaces, so a till never shows two "Drinks".
 */
final class SaveCategory
{
    /** @return string the category's ID */
    public function handle(?string $id, string $name, int $sortOrder): string
    {
        $name = trim($name);

        return DB::transaction(function () use ($id, $name, $sortOrder) {
            // One writer per tenant at a time, so two people can't add the same name together.
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':catalogue.categories'))");

            $taken = Category::query()
                ->whereRaw('lower(name) = lower(?)', [$name])
                ->when($id, fn ($query) => $query->whereKeyNot($id))
                ->exists();

            if ($taken) {
                throw new MenuConflict(MenuConflict::CATEGORY_NAME_TAKEN);
            }

            $category = $id === null ? new Category : Category::query()->findOrFail($id);
            $category->fill(['name' => $name, 'sort_order' => $sortOrder])->save();

            return $category->id;
        });
    }
}
