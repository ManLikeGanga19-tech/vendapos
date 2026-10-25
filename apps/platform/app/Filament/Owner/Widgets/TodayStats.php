<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesDay;
use App\Domain\Etims\Actions\CountOpenSubmissions;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use VendaPOS\Core\ValueObjects\Money;

/** The business day so far, from the rollups, plus documents not yet with KRA. */
class TodayStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ScopesToStaffBranches;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $branchIds = $this->selectedBranchIds();
        $day = app(SummariseSalesDay::class)->handle($this->businessToday(), $branchIds);
        $etims = app(CountOpenSubmissions::class)->handle($branchIds);

        return [
            Stat::make(__('owner.dashboard.sales'), Money::fromCents($day['total_cents'])->format())
                ->description(__('owner.dashboard.vat', ['vat' => Money::fromCents($day['vat_cents'])->format()])),
            Stat::make(__('owner.dashboard.orders'), (string) $day['orders']),
            Stat::make(__('owner.dashboard.voids_cancels'), "{$day['voids']} / {$day['cancels']}")
                ->description(__('owner.dashboard.voids_cancels_help')),
            Stat::make(__('owner.dashboard.etims'), (string) ($etims['pending'] + $etims['failed']))
                ->description($etims['failed'] > 0
                    ? __('owner.dashboard.etims_failed', ['count' => $etims['failed']])
                    : __('owner.dashboard.etims_help'))
                ->color($etims['failed'] > 0 ? 'danger' : null)
                ->icon($etims['failed'] > 0 ? 'heroicon-o-exclamation-triangle' : null),
        ];
    }
}
