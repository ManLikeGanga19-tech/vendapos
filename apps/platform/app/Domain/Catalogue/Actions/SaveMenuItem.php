<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Exceptions\MenuConflict;
use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Adds an item to the menu or changes one. One price for every branch.
 *
 * The type is chosen when the item is added and kept afterwards: a product
 * keeps stock and a service doesn't, so switching would strand its stock.
 * Bundles and modifiers come with the restaurant pack, not here.
 *
 * An SKU is unique across the whole menu; a barcode among items on sale, so
 * a scan always finds one item while an archived one can give its code up.
 */
final class SaveMenuItem
{
    /** Item types the menu screens manage. */
    public const TYPES = [ItemType::Product, ItemType::Service];

    /** KES 10,000,000: a guard against a slipped finger, far above any till price. */
    public const MAX_PRICE_CENTS = 1_000_000_000;

    /** @return string the item's ID */
    public function handle(
        ?string $id,
        string $name,
        ItemType $type,
        Money $price,
        VatCategory $vatCategory,
        ?string $categoryId = null,
        ?string $sku = null,
        ?string $barcode = null,
    ): string {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Items of type {$type->value} are not managed on the menu screens.");
        }

        if (! $price->isPositive() || $price->cents > self::MAX_PRICE_CENTS) {
            throw new InvalidArgumentException('An item needs a price above zero and at most KES 10,000,000.');
        }

        $sku = self::blankToNull($sku);
        $barcode = self::blankToNull($barcode);

        return DB::transaction(function () use ($id, $name, $type, $price, $vatCategory, $categoryId, $sku, $barcode) {
            // One writer per tenant at a time, so two saves can't claim the same code together.
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':catalogue.items'))");

            $item = $id === null ? new Item(['type' => $type, 'is_active' => true]) : Item::query()->findOrFail($id);

            if ($categoryId !== null && ! Category::query()->whereKey($categoryId)->exists()) {
                throw new InvalidArgumentException('That category is not on this menu.');
            }

            if ($sku !== null && Item::query()->where('sku', $sku)->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw new MenuConflict(MenuConflict::SKU_TAKEN);
            }

            if ($barcode !== null && $item->is_active && self::barcodeOnSale($barcode, $id)) {
                throw new MenuConflict(MenuConflict::BARCODE_TAKEN);
            }

            $item->fill([
                'name' => trim($name),
                'category_id' => $categoryId,
                'price_cents' => $price->cents,
                'vat_category' => $vatCategory,
                'sku' => $sku,
                'barcode' => $barcode,
            ])->save();

            return $item->id;
        });
    }

    /** Whether an item on sale, other than $exceptId, already carries this barcode. Run under the items lock. */
    public static function barcodeOnSale(string $barcode, ?string $exceptId): bool
    {
        return Item::query()
            ->active()
            ->where('barcode', $barcode)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
