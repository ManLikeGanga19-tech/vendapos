<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\Category;
use App\Models\Item;
use App\Models\SyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Brings the local menu up to date: the first run downloads everything, later
 * runs only what changed since the server's last `synced_at`.
 */
final class SyncCatalogue
{
    public const string CURSOR = 'catalogue.synced_at';

    private const int CHUNK = 500;

    public function __construct(private readonly VendaApi $api) {}

    public function handle(): void
    {
        $cursor = SyncState::read(self::CURSOR);
        $snapshot = $this->api->catalogue($cursor === null ? null : CarbonImmutable::parse($cursor));

        DB::transaction(function () use ($snapshot): void {
            // Categories always arrive in full.
            Category::query()->delete();
            Category::query()->insert($snapshot->categories);

            if ($snapshot->full) {
                Item::query()->delete();
            }

            // Upsert, not insert: changes include items switched off, so is_active is overwritten too.
            // Chunked to stay under SQLite's limit on bound values for a supermarket-sized menu.
            foreach (array_chunk($snapshot->items, self::CHUNK) as $chunk) {
                Item::query()->upsert($chunk, ['id']);
            }

            SyncState::write(self::CURSOR, $snapshot->syncedAt->toIso8601String());
        });
    }
}
