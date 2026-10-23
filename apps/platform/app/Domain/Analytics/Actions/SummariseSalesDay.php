<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\SalesHourly;

/**
 * The current tenant's sales for one Africa/Nairobi business day, across all
 * branches or only the given ones, read from the hourly rollup (never from
 * the orders tables).
 */
final class SummariseSalesDay
{
    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @return array{orders: int, voids: int, cancels: int, total_cents: int, vat_cents: int, hourly_total_cents: array<int, int>}
     */
    public function handle(string $businessDate, ?array $branchIds = null): array
    {
        $hours = SalesHourly::query()
            ->whereDate('business_date', $businessDate)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get();

        $hourly = array_fill(0, 24, 0);
        foreach ($hours as $hour) {
            $hourly[$hour->hour] += $hour->total_cents;
        }

        return [
            'orders' => (int) $hours->sum('order_count'),
            'voids' => (int) $hours->sum('void_count'),
            'cancels' => (int) $hours->sum('cancelled_count'),
            'total_cents' => (int) $hours->sum('total_cents'),
            'vat_cents' => (int) $hours->sum('vat_cents'),
            'hourly_total_cents' => $hourly,
        ];
    }
}
