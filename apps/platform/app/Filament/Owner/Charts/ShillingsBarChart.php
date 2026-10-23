<?php

declare(strict_types=1);

namespace App\Filament\Owner\Charts;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A single-series bar chart of KES amounts for the Owner panel: one series,
 * so no legend (the heading names it). The amber passes 3:1 contrast and the
 * lightness band on both light and dark chart surfaces; bars are square
 * (no rounded corners anywhere in VendaPOS). Chart.js shows a tooltip per
 * bar, and the same figures are given as text for screen readers.
 */
abstract class ShillingsBarChart extends ChartWidget
{
    private const string BAR_COLOR = '#d97706';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /** @var array<string, int>|null label => whole shillings, read once per render */
    private ?array $bars = null;

    /**
     * Each bar's label and amount in cents, in display order.
     *
     * @return array<string, int>
     */
    abstract protected function barsInCents(): array;

    abstract protected function emptyText(): string;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $bars = $this->bars();

        return [
            'datasets' => [[
                'label' => (string) __('owner.dashboard.sales_kes'),
                'data' => array_values($bars),
                'backgroundColor' => self::BAR_COLOR,
                'borderWidth' => 0,
                'borderRadius' => 0,
                'maxBarThickness' => 24,
            ]],
            'labels' => array_map('strval', array_keys($bars)),
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

    /** A text version of the chart for screen readers: every bar with sales. */
    public function getChartAssistiveContent(): string|Htmlable|null
    {
        $bars = array_filter($this->bars(), fn (int $shillings) => $shillings !== 0);

        if ($bars === []) {
            return $this->emptyText();
        }

        return implode('; ', array_map(
            fn (string|int $label, int $shillings) => sprintf('%s KES %s', $label, number_format($shillings)),
            array_keys($bars),
            $bars,
        ));
    }

    /** @return array<string, int> */
    private function bars(): array
    {
        // Rounded to whole shillings for display only (CLAUDE.md §4).
        return $this->bars ??= array_map(fn (int $cents) => (int) round($cents / 100), $this->barsInCents());
    }
}
