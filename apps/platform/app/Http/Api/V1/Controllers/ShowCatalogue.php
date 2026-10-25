<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use App\Http\Api\V1\Requests\ShowCatalogueRequest;
use Illuminate\Http\JsonResponse;

/**
 * The terminal's offline menu snapshot. Without `updated_since` it returns
 * every active item; with it, every item changed since then, including ones
 * deactivated, so the terminal can drop them.
 */
final class ShowCatalogue
{
    /**
     * How far `synced_at` reaches back. updated_at keeps whole seconds, and a
     * save still committing while this request reads is invisible to it; both
     * would otherwise carry a time at or before `synced_at` and never be sent.
     * The terminal receives those few items twice and simply overwrites them.
     */
    private const OVERLAP_SECONDS = 5;

    public function __invoke(ShowCatalogueRequest $request): JsonResponse
    {
        // Taken before reading, so a change made during this request is picked up next time.
        $syncedAt = now()->subSeconds(self::OVERLAP_SECONDS);
        $since = $request->date('updated_since');

        $items = Item::query()
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since), fn ($query) => $query->active())
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'type' => $item->type->value,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'price_cents' => $item->price_cents,
                'vat_category' => $item->vat_category->value,
                'is_active' => $item->is_active,
            ]);

        return response()->json([
            'synced_at' => $syncedAt->toIso8601String(),
            'full' => $since === null,
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'sort_order']),
            'items' => $items,
        ]);
    }
}
