<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\DiningTable;
use App\Models\FloorArea;
use Illuminate\Support\Facades\DB;

/** Replaces this till's copy of the branch's floor plan; it is small, so always in full. */
final class SyncFloor
{
    public function __construct(private readonly VendaApi $api) {}

    public function handle(): void
    {
        $areas = $this->api->floor();

        DB::transaction(function () use ($areas): void {
            DiningTable::query()->delete();
            FloorArea::query()->delete();

            foreach ($areas as $area) {
                FloorArea::query()->create(['id' => $area['id'], 'name' => $area['name'], 'sort_order' => $area['sort_order']]);

                foreach ($area['tables'] as $table) {
                    DiningTable::query()->create([...$table, 'floor_area_id' => $area['id']]);
                }
            }
        });
    }
}
