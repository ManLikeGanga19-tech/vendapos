<?php

declare(strict_types=1);

namespace App\CashUp;

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\CashCountData;
use VendaPOS\Core\Enums\KesDenomination;

/** A drawer count as typed on the screen, checked with the rules the server uses. */
final class Count
{
    /**
     * @param  array<int|string, mixed>  $typed  how many of each note and coin, by shillings; blank means none
     *
     * @throws CashUpRefused
     */
    public static function of(array $typed): CashCountData
    {
        $counts = [];

        foreach (KesDenomination::cases() as $denomination) {
            $value = $typed[$denomination->value] ?? $typed[(string) $denomination->value] ?? '';
            $counts[$denomination->value] = $value === '' ? 0 : $value;
        }

        if (Validator::make(['counts' => $counts], CashCountData::rules())->fails()) {
            throw new CashUpRefused('count');
        }

        return new CashCountData(array_filter(array_map(intval(...), $counts), fn (int $count) => $count > 0));
    }
}
