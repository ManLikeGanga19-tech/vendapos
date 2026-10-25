<?php

declare(strict_types=1);

namespace App\Filament\Owner\Reports\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesPeriod;
use App\Filament\Owner\Concerns\ResolvesReportFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;
use VendaPOS\Core\ValueObjects\Money;

/** The Sales report's headline figures for the filtered period. */
class SalesKpis extends StatsOverviewWidget
{
    use ResolvesReportFilters;

    /** @var array<string, mixed>|null the report page's filters */
    #[Reactive]
    public ?array $tableFilters = null;

    protected function getStats(): array
    {
        [$from, $to] = $this->rangeFrom($this->tableFilters);
        $period = app(SummariseSalesPeriod::class)->handle($from, $to, $this->reportBranchIds($this->tableFilters));

        // Average of completed orders; rounded to the cent for display only.
        $average = $period['orders'] > 0 ? (int) round($period['total_cents'] / $period['orders']) : 0;

        return [
            Stat::make(__('owner.reports.kpi.sales'), Money::fromCents($period['total_cents'])->format())
                ->description(__('owner.dashboard.vat', ['vat' => Money::fromCents($period['vat_cents'])->format()])),
            Stat::make(__('owner.dashboard.orders'), number_format($period['orders'])),
            Stat::make(__('owner.reports.kpi.average_order'), Money::fromCents($average)->format())
                ->description(__('owner.reports.kpi.average_order_help')),
            Stat::make(__('owner.dashboard.voids_cancels'), "{$period['voids']} / {$period['cancels']}")
                ->description(__('owner.dashboard.voids_cancels_help')),
        ];
    }
}
