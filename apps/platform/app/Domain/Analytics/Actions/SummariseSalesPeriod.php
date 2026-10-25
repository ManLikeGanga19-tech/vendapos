<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\SalesHourly;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The current tenant's totals over a range of Africa/Nairobi business days,
 * plus sales by hour of day across the range, from the hourly rollup in one
 * grouped query.
 */
final class SummariseSalesPeriod
{
    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @return array{orders: int, voids: int, cancels: int, subtotal_cents: int, vat_cents: int, total_cents: int, hourly_total_cents: array<int, int>}
     */
    public function handle(string $fromDate, string $toDate, ?array $branchIds = null): array
    {
        $from = CarbonImmutable::parse($fromDate)->startOfDay();
        $to = CarbonImmutable::parse($toDate)->startOfDay();

        if ($from->gt($to) || $from->diffInDays($to) + 1 > SummariseSalesByDay::MAX_DAYS) {
            throw new InvalidArgumentException('A range runs forward and covers at most '.SummariseSalesByDay::MAX_DAYS.' days.');
        }

        $hours = SalesHourly::query()
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->groupBy('hour')
            ->selectRaw('hour, sum(order_count) as orders, sum(void_count) as voids, sum(cancelled_count) as cancels,
                sum(subtotal_cents) as subtotal_cents, sum(vat_cents) as vat_cents, sum(total_cents) as total_cents')
            ->toBase()
            ->get();

        $hourly = array_fill(0, 24, 0);
        foreach ($hours as $hour) {
            $hourly[(int) $hour->hour] = (int) $hour->total_cents;
        }

        return [
            'orders' => (int) $hours->sum('orders'),
            'voids' => (int) $hours->sum('voids'),
            'cancels' => (int) $hours->sum('cancels'),
            'subtotal_cents' => (int) $hours->sum('subtotal_cents'),
            'vat_cents' => (int) $hours->sum('vat_cents'),
            'total_cents' => (int) $hours->sum('total_cents'),
            'hourly_total_cents' => $hourly,
        ];
    }
}
