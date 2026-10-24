<?php

declare(strict_types=1);

namespace App\Filament\Owner\Charts;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A single-series chart of KES amounts for the Owner panel: one series, so
 * no legend (the heading names it). The amber passes 3:1 contrast and the
 * lightness band on both light and dark chart surfaces. Bars are square (no
 * rounded corners anywhere in VendaPOS); lines are 2px, straight between
 * points (smoothing would invent values) and unfilled. Hovering anywhere
 * shows the nearest point's tooltip, and the same figures are given as text
 * for screen readers.
 */
abstract class ShillingsChart extends ChartWidget
{
    private const string COLOR = '#d97706';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /** @var array<string, int>|null label => whole shillings, read once per render */
    private ?array $points = null;

    /**
     * Each point's label and amount in cents, in display order.
     *
     * @return array<string, int>
     */
    abstract protected function pointsInCents(): array;

    abstract protected function emptyText(): string;

    /** 'bar' for amounts per slot, 'line' for how they move across a range. */
    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $points = $this->points();
        $series = $this->getType() === 'line'
            ? [
                'borderColor' => self::COLOR,
                'backgroundColor' => self::COLOR,
                'borderWidth' => 2,
                'tension' => 0,
                'fill' => false,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
                'pointHitRadius' => 10,
            ]
            : [
                'backgroundColor' => self::COLOR,
                'borderWidth' => 0,
                'borderRadius' => 0,
                'maxBarThickness' => 24,
            ];

        return [
            'datasets' => [[
                'label' => (string) __('owner.dashboard.sales_kes'),
                'data' => array_values($points),
                ...$series,
            ]],
            'labels' => array_map('strval', array_keys($points)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            // The nearest point along the x-axis answers the hover, not only an exact hit.
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxRotation' => 0, 'autoSkip' => true]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    /** A text version of the chart for screen readers: every point with sales. */
    public function getChartAssistiveContent(): string|Htmlable|null
    {
        $points = array_filter($this->points(), fn (int $shillings) => $shillings !== 0);

        if ($points === []) {
            return $this->emptyText();
        }

        return implode('; ', array_map(
            fn (string|int $label, int $shillings) => sprintf('%s KES %s', $label, number_format($shillings)),
            array_keys($points),
            $points,
        ));
    }

    /** @return array<string, int> */
    private function points(): array
    {
        // Rounded to whole shillings for display only (CLAUDE.md §4).
        return $this->points ??= array_map(fn (int $cents) => (int) round($cents / 100), $this->pointsInCents());
    }
}
