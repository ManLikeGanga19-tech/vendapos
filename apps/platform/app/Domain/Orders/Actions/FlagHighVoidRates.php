<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\VoidCheck;
use Carbon\CarbonImmutable;
use VendaPOS\Core\Enums\OrderStatus;

/**
 * Architecture §10's void-rate rule, per staff member and business day
 * (Daniel, T5a-2): someone whose voided sales were more than the limit share
 * of the sales they made that day, at one branch, once they made enough sales
 * for the share to mean something. Run for a finished day; safe to run again,
 * so a till that syncs late is still counted.
 */
final class FlagHighVoidRates
{
    /** @return int checks opened (or found again) */
    public function handle(CarbonImmutable $businessDate): int
    {
        $timezone = (string) config('vendapos.business_timezone');
        $percent = (int) config('vendapos.voids.rate_percent');
        $minSales = (int) config('vendapos.voids.rate_min_sales');
        $start = CarbonImmutable::parse($businessDate->toDateString(), $timezone)->startOfDay();

        $rows = Order::query()->toBase()
            ->selectRaw('branch_id, staff_id, count(*) as sales, sum(case when status = ? then 1 else 0 end) as voided', [OrderStatus::Voided->value])
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Voided->value])
            ->whereNotNull('staff_id')
            ->where('placed_at', '>=', $start->utc())
            ->where('placed_at', '<', $start->addDay()->utc())
            ->groupBy('branch_id', 'staff_id')
            ->havingRaw('count(*) >= ?', [$minSales])
            ->get();

        $flagged = 0;

        foreach ($rows as $row) {
            $sales = (int) $row->sales;
            $voided = (int) $row->voided;

            // "More than 10%": exactly the limit is not flagged.
            if ($voided * 100 <= $sales * $percent) {
                continue;
            }

            VoidCheck::open(VoidCheck::HIGH_VOID_RATE, "{$row->branch_id}:{$row->staff_id}:{$businessDate->toDateString()}", [
                'branch_id' => (string) $row->branch_id,
                'staff_id' => (string) $row->staff_id,
                'business_date' => $businessDate->toDateString(),
                'details' => ['sales' => $sales, 'voided' => $voided, 'percent' => intdiv($voided * 100, $sales)],
            ]);
            $flagged++;
        }

        return $flagged;
    }
}
