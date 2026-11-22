<?php

declare(strict_types=1);

namespace App\Api\Data;

use Carbon\CarbonImmutable;

/**
 * Categories always come in full. Items come in full (active only) when
 * `full`, otherwise only those changed since the last sync, inactive included.
 */
final readonly class CatalogueSnapshot
{
    /**
     * @param  list<array{id: string, name: string, sort_order: int}>  $categories
     * @param  list<array{id: string, category_id: string|null, type: string, name: string, sku: string|null, barcode: string|null, price_cents: int, vat_category: string, is_active: bool}>  $items
     */
    public function __construct(
        public CarbonImmutable $syncedAt,
        public bool $full,
        public array $categories,
        public array $items,
    ) {}
}
