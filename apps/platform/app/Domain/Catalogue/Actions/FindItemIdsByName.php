<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Item;

/**
 * The public way other code finds items by part of their name, ignoring
 * case. The search text is matched literally: % and _ are not wildcards.
 */
final class FindItemIdsByName
{
    /** @return list<string> */
    public function handle(string $search, int $limit = 500): array
    {
        $literal = addcslashes(trim($search), '\\%_');

        return array_values(Item::query()
            ->where('name', 'ilike', "%{$literal}%")
            ->orderBy('name')
            ->limit($limit)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
    }
}
