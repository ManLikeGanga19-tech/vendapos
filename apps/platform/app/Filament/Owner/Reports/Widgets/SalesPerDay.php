<?php

declare(strict_types=1);

namespace App\Filament\Owner\Reports\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Filament\Owner\Charts\ShillingsChart;
use App\Filament\Owner\Concerns\ResolvesReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Reactive;

/**
 * Sales for each business day of the filtered period, oldest on the left.
 * At most 92 points, from one grouped query over the rollup.
 */
class SalesPerDay extends ShillingsChart
{
    use ResolvesReportFilters;

    /** @var array<string, mixed>|null the report page's filters */
    #[Reactive]
    public ?array $tableFilters = null;

    /** Half the width beside its partner chart; full width on phones. */
    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('owner.reports.sales_per_day');
    }

    protected function pointsInCents(): array
    {
        [$from, $to] = $this->rangeFrom($this->tableFilters);
        $days = array_reverse(app(SummariseSalesByDay::class)->handle($from, $to, $this->reportBranchIds($this->tableFilters)));

        $points = [];
        foreach ($days as $date => $day) {
            $points[CarbonImmutable::parse($date)->translatedFormat('D j M')] = $day['total_cents'];
        }

        return $points;
    }

    protected function emptyText(): string
    {
        return __('owner.reports.no_sales');
    }
}
