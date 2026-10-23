<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesDay;
use App\Filament\Owner\Charts\ShillingsBarChart;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/** Today's sales per Africa/Nairobi hour. */
class SalesByHour extends ShillingsBarChart
{
    use InteractsWithPageFilters;
    use ScopesToStaffBranches;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    public function getHeading(): string|Htmlable|null
    {
        return __('owner.dashboard.sales_by_hour');
    }

    protected function barsInCents(): array
    {
        $cents = app(SummariseSalesDay::class)->handle($this->businessToday(), $this->selectedBranchIds())['hourly_total_cents'];

        return array_combine(array_map(fn (int $hour) => sprintf('%02d:00', $hour), range(0, 23)), $cents);
    }

    protected function emptyText(): string
    {
        return __('owner.dashboard.no_sales');
    }
}
