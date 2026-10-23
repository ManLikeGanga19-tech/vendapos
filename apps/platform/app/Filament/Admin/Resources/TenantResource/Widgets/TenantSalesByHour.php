<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Widgets;

use App\Domain\Analytics\Actions\SummariseSalesDay;
use App\Domain\Tenancy\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

/**
 * Today's sales per Africa/Nairobi hour for one tenant: one series, so no
 * legend. The amber was checked for contrast on light and dark surfaces;
 * bars are square (no rounded corners anywhere in VendaPOS).
 */
class TenantSalesByHour extends ChartWidget
{
    // Passes 3:1 contrast and the lightness band on both light and dark chart surfaces.
    private const string BAR_COLOR = '#d97706';

    #[Locked]
    public ?Tenant $record = null;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /** @var array<int, int>|null whole shillings per hour, read once per render */
    private ?array $hourly = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.today.sales_by_hour');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [[
                'label' => __('admin.today.sales_kes'),
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
            return __('admin.today.no_sales');
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

        if ($this->record === null) {
            return $this->hourly = array_fill(0, 24, 0);
        }

        $today = now((string) config('vendapos.business_timezone'))->toDateString();
        $cents = $this->record->run(fn () => app(SummariseSalesDay::class)->handle($today)['hourly_total_cents']);

        // Rounded to whole shillings for display only (CLAUDE.md §4).
        return $this->hourly = array_map(fn (int $c) => (int) round($c / 100), $cents);
    }
}
