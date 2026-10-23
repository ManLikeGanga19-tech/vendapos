<?php

declare(strict_types=1);

namespace App\Filament\Owner\Concerns;

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Domain\Tenancy\Actions\ListBranches;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

/**
 * Period and branch filters for Owner reports. Periods are Africa/Nairobi
 * business days. The browser sends the filter state, so the branch only
 * ever narrows the person's own branches and a bad range falls back to today.
 */
trait HasReportFilters
{
    use ScopesToStaffBranches;

    public const array PERIODS = ['today', 'yesterday', 'last_7_days', 'this_month', 'custom'];

    /** @return list<BaseFilter> */
    protected function reportFilters(): array
    {
        $filters = [
            Filter::make('period')
                ->schema([
                    Select::make('period')
                        ->label(__('owner.reports.period'))
                        ->options(array_combine(self::PERIODS, array_map(fn (string $p) => __("owner.reports.periods.{$p}"), self::PERIODS)))
                        ->default('today')
                        ->selectablePlaceholder(false)
                        // Native: the custom dropdown was clipped inside the
                        // filters area, and phones show their own picker.
                        ->native()
                        ->live(),
                    DatePicker::make('from')
                        ->label(__('owner.reports.from'))
                        ->native(false)
                        ->maxDate(fn () => $this->businessToday())
                        ->visible(fn (Get $get) => $get('period') === 'custom'),
                    DatePicker::make('until')
                        ->label(__('owner.reports.until'))
                        ->native(false)
                        ->maxDate(fn () => $this->businessToday())
                        ->visible(fn (Get $get) => $get('period') === 'custom'),
                ])
                ->indicateUsing(function (array $data): string {
                    [$from, $to] = $this->rangeFrom(['period' => $data]);

                    return __('owner.reports.range', ['from' => $this->dayLabel($from), 'to' => $this->dayLabel($to)]);
                }),
        ];

        $branches = array_map(fn (array $b) => $b['name'], app(ListBranches::class)->handle($this->visibleBranchIds()));

        if (count($branches) > 1) {
            $filters[] = SelectFilter::make('branch')
                ->label(__('owner.dashboard.branch'))
                ->placeholder(__('owner.dashboard.all_branches'))
                ->options($branches);
        }

        return $filters;
    }

    /**
     * The business days the filters ask for, oldest first.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{0: string, 1: string}
     */
    protected function rangeFrom(?array $filters): array
    {
        $today = CarbonImmutable::parse($this->businessToday());
        $period = $filters['period'] ?? [];

        [$from, $to] = match ($period['period'] ?? 'today') {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last_7_days' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'custom' => [$this->dateOrNull($period['from'] ?? null) ?? $today, $this->dateOrNull($period['until'] ?? null) ?? $today],
            default => [$today, $today],
        };

        $to = $to->gt($today) ? $today : $to;

        if ($from->gt($to) || $from->diffInDays($to) + 1 > SummariseSalesByDay::MAX_DAYS) {
            return [$today->toDateString(), $today->toDateString()];
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return list<string>
     */
    protected function reportBranchIds(?array $filters): array
    {
        $picked = $filters['branch']['value'] ?? null;

        return is_string($picked) && in_array($picked, $this->visibleBranchIds(), true)
            ? [$picked]
            : $this->visibleBranchIds();
    }

    protected function dayLabel(string $date): string
    {
        // Day and month names in the app's language (Laravel keeps Carbon's locale in step).
        return CarbonImmutable::parse($date)->translatedFormat('D j M Y');
    }

    private function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::parse(substr($value, 0, 10));
    }
}
