<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\KesDenomination;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Notes and coins counted in a drawer: how many of each denomination, keyed
 * by its value in shillings ("1000" => 3). The total is always worked out
 * from the counts, never sent, so it cannot disagree with them.
 */
final class CashCountData extends Data
{
    // More than any drawer holds of one note or coin; stops a typo becoming millions.
    public const int MAX_PER_DENOMINATION = 10_000;

    /** @param  array<int|string, int>  $counts */
    public function __construct(
        public array $counts,
    ) {}

    public function total(): Money
    {
        $total = Money::zero();

        foreach ($this->counts as $shillings => $count) {
            $total = $total->add(KesDenomination::from((int) $shillings)->value()->multiply($count));
        }

        return $total;
    }

    public function countOf(KesDenomination $denomination): int
    {
        return (int) ($this->counts[$denomination->value] ?? $this->counts[(string) $denomination->value] ?? 0);
    }

    /**
     * Rules for a count on its own, or nested under $prefix (e.g. "opening_float.").
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}counts" => ['required', 'array:'.implode(',', KesDenomination::shillings())],
            "{$prefix}counts.*" => ['required', 'integer', 'min:0', 'max:'.self::MAX_PER_DENOMINATION],
        ];
    }
}
