<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Exceptions\MenuConflict;
use App\Domain\Catalogue\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Archives an item (off every till, history kept) or puts it back on sale.
 * Items are never deleted: past orders and stock movements point at them.
 */
final class SetMenuItemOnSale
{
    public function handle(string $id, bool $onSale): void
    {
        DB::transaction(function () use ($id, $onSale) {
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':catalogue.items'))");

            $item = Item::query()->findOrFail($id);

            // While archived, another item may have taken its barcode.
            if ($onSale && $item->barcode !== null && SaveMenuItem::barcodeOnSale($item->barcode, $item->id)) {
                throw new MenuConflict(MenuConflict::BARCODE_TAKEN);
            }

            $item->update(['is_active' => $onSale]);
        });
    }
}
