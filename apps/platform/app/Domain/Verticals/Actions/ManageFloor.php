<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Actions;

use App\Domain\Verticals\Exceptions\FloorChangeRefused;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\FloorArea;
use Illuminate\Support\Facades\DB;

/**
 * Areas and tables of a branch's floor (table service pack). Table names are
 * unique per branch. A table is switched off, never deleted, so past tickets
 * keep pointing at it; an area can only be removed once it has no tables.
 * Who may change which branch is checked by the caller.
 */
final class ManageFloor
{
    /** Tables one "add" may create, e.g. "1-40". */
    public const int MAX_TABLES_AT_ONCE = 100;

    /** @throws FloorChangeRefused */
    public function saveArea(?string $areaId, string $branchId, string $name): FloorArea
    {
        $name = trim($name);
        $area = $areaId !== null ? FloorArea::query()->find($areaId) : new FloorArea(['branch_id' => $branchId]);

        if ($area === null || $area->branch_id !== $branchId) {
            throw new FloorChangeRefused('not_found');
        }

        if (FloorArea::query()->where('branch_id', $branchId)->where('name', $name)->when($area->exists, fn ($q) => $q->whereKeyNot($area->id))->exists()) {
            throw new FloorChangeRefused('name_taken');
        }

        $area->fill(['name' => $name, 'sort_order' => $area->sort_order ?? (int) FloorArea::query()->where('branch_id', $branchId)->max('sort_order') + 1])->save();

        return $area;
    }

    /** @throws FloorChangeRefused */
    public function deleteArea(string $areaId): void
    {
        $area = FloorArea::query()->find($areaId) ?? throw new FloorChangeRefused('not_found');

        if ($area->tables()->exists()) {
            throw new FloorChangeRefused('area_not_empty');
        }

        $area->delete();
    }

    /**
     * Adds tables named by $names: "1-12", "T1-T12", or a list such as "Bar 1, Bar 2".
     *
     * @return list<DiningTable>
     *
     * @throws FloorChangeRefused
     */
    public function addTables(string $areaId, string $names, int $seats): array
    {
        $area = FloorArea::query()->find($areaId) ?? throw new FloorChangeRefused('not_found');
        $list = self::namesFrom($names);

        if ($list === [] || count($list) > self::MAX_TABLES_AT_ONCE) {
            throw new FloorChangeRefused('bad_names');
        }

        if (DiningTable::query()->where('branch_id', $area->branch_id)->whereIn('name', $list)->exists()) {
            throw new FloorChangeRefused('name_taken');
        }

        return DB::transaction(function () use ($area, $list, $seats) {
            $order = (int) DiningTable::query()->where('floor_area_id', $area->id)->max('sort_order');

            return array_map(fn (string $name) => DiningTable::query()->create([
                'branch_id' => $area->branch_id,
                'floor_area_id' => $area->id,
                'name' => $name,
                'seats' => max(1, min($seats, 99)),
                'sort_order' => ++$order,
            ]), $list);
        });
    }

    /** @throws FloorChangeRefused */
    public function updateTable(string $tableId, string $name, int $seats, string $areaId): DiningTable
    {
        $table = DiningTable::query()->find($tableId) ?? throw new FloorChangeRefused('not_found');
        $area = FloorArea::query()->find($areaId);
        $name = trim($name);

        if ($area === null || $area->branch_id !== $table->branch_id || $name === '' || mb_strlen($name) > 20) {
            throw new FloorChangeRefused('not_found');
        }

        if (DiningTable::query()->where('branch_id', $table->branch_id)->where('name', $name)->whereKeyNot($table->id)->exists()) {
            throw new FloorChangeRefused('name_taken');
        }

        $table->update(['name' => $name, 'seats' => max(1, min($seats, 99)), 'floor_area_id' => $area->id]);

        return $table;
    }

    /** @throws FloorChangeRefused */
    public function setTableActive(string $tableId, bool $active): void
    {
        $table = DiningTable::query()->find($tableId) ?? throw new FloorChangeRefused('not_found');
        $table->update(['is_active' => $active]);
    }

    /**
     * "1-12" and "T1-T12" are ranges (same prefix, at most MAX_TABLES_AT_ONCE);
     * anything else is a comma-separated list. Names are trimmed and de-duplicated.
     *
     * @return list<string>
     */
    public static function namesFrom(string $input): array
    {
        $input = trim($input);

        // The prefix keeps its spacing: "Bar 1-Bar 5" gives "Bar 1" … "Bar 5".
        if (preg_match('/^([A-Za-z ]*)(\d{1,4})\s*-\s*(?:\1)?(\d{1,4})$/', $input, $m) === 1) {
            [$prefix, $from, $to] = [$m[1], (int) $m[2], (int) $m[3]];

            if ($to < $from || $to - $from >= self::MAX_TABLES_AT_ONCE) {
                return [];
            }

            return array_map(fn (int $n) => $prefix === '' ? (string) $n : "{$prefix}{$n}", range($from, $to));
        }

        $names = array_values(array_unique(array_filter(array_map(trim(...), explode(',', $input)), fn (string $name) => $name !== '')));

        return array_values(array_filter($names, fn (string $name) => mb_strlen($name) <= 20)) === $names ? $names : [];
    }
}
