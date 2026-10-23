<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesDay;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Today's sales per Africa/Nairobi hour: one series, so no legend. Same
 * contrast-checked amber and square bars as the Admin panel's chart.
 */
class SalesByHour extends ChartWidget
{
    use InteractsWithPageFilters;
    use ScopesToStaffBranches;

    // Passes 3:1 contrast and the lightness band on both light and dark chart surfaces.
    private const string BAR_COLOR = '#d97706';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /** @var array<int, int>|null whole shillings per hour, read once per render */
    private ?array $hourly = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('owner.dashboard.sales_by_hour');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [[
                'label' => __('owner.dashboard.sales_kes'),
                'data' => array_values($this->hourly()),
                'backgroundColor' => self::BAR_COLOR,
                'borderWidth' => 0,
                'borderRadius' => 0,
                'maxBarThickness' => 24,
            ]],
            'labels' => array_map(fn (int $hour) => sprintf('%02d:00', $hour), range(0, 23)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    /** A text version of the chart for screen readers. */
    public function getChartAssistiveContent(): string|Htmlable|null
    {
        $hours = array_filter($this->hourly(), fn (int $shillings) => $shillings !== 0);

        if ($hours === []) {
            return __('owner.dashboard.no_sales');
        }

        return implode('; ', array_map(
            fn (int $hour, int $shillings) => sprintf('%02d:00 KES %s', $hour, number_format($shillings)),
            array_keys($hours),
            $hours,
        ));
    }

    /** @return array<int, int> */
    private function hourly(): array
    {
        if ($this->hourly !== null) {
            return $this->hourly;
        }

        $cents = app(SummariseSalesDay::class)->handle($this->businessToday(), $this->selectedBranchIds())['hourly_total_cents'];

        // Rounded to whole shillings for display only (CLAUDE.md §4).
        return $this->hourly = array_map(fn (int $c) => (int) round($c / 100), $cents);
    }
}
