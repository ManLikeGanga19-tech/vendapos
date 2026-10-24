<?php

declare(strict_types=1);

namespace App\Filament\Owner\Reports\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesPeriod;
use App\Filament\Owner\Charts\ShillingsChart;
use App\Filament\Owner\Concerns\ResolvesReportFilters;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Reactive;

/** Sales by Africa/Nairobi hour of day across the filtered period: when the business is busiest. */
class BusiestHours extends ShillingsChart
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
        return __('owner.reports.busiest_hours');
    }

    protected function pointsInCents(): array
    {
        [$from, $to] = $this->rangeFrom($this->tableFilters);
        $cents = app(SummariseSalesPeriod::class)->handle($from, $to, $this->reportBranchIds($this->tableFilters))['hourly_total_cents'];

        return array_combine(array_map(fn (int $hour) => sprintf('%02d:00', $hour), range(0, 23)), $cents);
    }

    protected function emptyText(): string
    {
        return __('owner.reports.no_sales');
    }
}
