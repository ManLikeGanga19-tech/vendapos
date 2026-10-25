<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\SalesHourly;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The current tenant's sales for each Africa/Nairobi business day in a range,
 * read from the hourly rollup. Every day in the range is returned, newest
 * first, with zeros for days without sales, so a closed day still shows.
 */
final class SummariseSalesByDay
{
    /** Longest range one call may cover; longer reports belong in an export. */
    public const int MAX_DAYS = 92;

    /**
     * Keyed by business date (Y-m-d).
     *
     * @param  list<string>|null  $branchIds  null for every branch
     * @return array<string, array{date: string, orders: int, voids: int, cancels: int, subtotal_cents: int, vat_cents: int, total_cents: int}>
     */
    public function handle(string $fromDate, string $toDate, ?array $branchIds = null): array
    {
        $from = CarbonImmutable::parse($fromDate)->startOfDay();
        $to = CarbonImmutable::parse($toDate)->startOfDay();

        if ($from->gt($to) || $from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw new InvalidArgumentException('A range runs forward and covers at most '.self::MAX_DAYS.' days.');
        }

        $totals = SalesHourly::query()
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->groupBy('business_date')
            ->selectRaw('business_date, sum(order_count) as orders, sum(void_count) as voids, sum(cancelled_count) as cancels,
                sum(subtotal_cents) as subtotal_cents, sum(vat_cents) as vat_cents, sum(total_cents) as total_cents')
            ->toBase()
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse((string) $row->business_date)->toDateString());

        $days = [];
        for ($day = $to; $day->gte($from); $day = $day->subDay()) {
            $date = $day->toDateString();
            $row = $totals->get($date);

            $days[$date] = [
                'date' => $date,
                'orders' => (int) ($row->orders ?? 0),
                'voids' => (int) ($row->voids ?? 0),
                'cancels' => (int) ($row->cancels ?? 0),
                'subtotal_cents' => (int) ($row->subtotal_cents ?? 0),
                'vat_cents' => (int) ($row->vat_cents ?? 0),
                'total_cents' => (int) ($row->total_cents ?? 0),
            ];
        }

        return $days;
    }
}
