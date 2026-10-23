<?php

declare(strict_types=1);

namespace App\Filament\Owner\Concerns;

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Domain\Tenancy\Actions\ListBranches;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

/**
 * Period and branch filters for Owner reports. Periods are Africa/Nairobi
 * business days and weeks start on Monday. Filters apply as soon as they
 * change and live in the URL, so a refresh or a shared link shows the same
 * report. The browser sends the filter state, so the branch only ever
 * narrows the person's own branches and a bad range falls back to today.
 */
trait HasReportFilters
{
    use ScopesToStaffBranches;

    public const array PERIODS = ['today', 'yesterday', 'this_week', 'last_7_days', 'this_month', 'last_month', 'custom'];

    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /** Applies the filters to a report table: live, kept in the session, laid out above the rows. */
    protected function withReportFilters(Table $table, BaseFilter ...$extra): Table
    {
        return $table
            ->filters([...$this->reportFilters(), ...$extra], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->deferFilters(false)
            ->persistFiltersInSession();
    }

    /** @return list<BaseFilter> */
    protected function reportFilters(): array
    {
        $today = fn (): CarbonImmutable => CarbonImmutable::parse($this->businessToday());
        $span = SummariseSalesByDay::MAX_DAYS - 1;

        $filters = [
            Filter::make('period')
                ->columnSpanFull()
                ->columns(['md' => 2, 'xl' => 4])
                ->schema([
                    ToggleButtons::make('period')
                        ->hiddenLabel()
                        ->options(array_combine(self::PERIODS, array_map(fn (string $p) => __("owner.reports.periods.{$p}"), self::PERIODS)))
                        ->default('today')
                        ->formatStateUsing(fn (?string $state): string => in_array($state, self::PERIODS, true) ? $state : 'today')
                        ->grouped()
                        ->live()
                        // Switching to Custom starts from the range on screen, ready to adjust.
                        ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                            if ($state !== 'custom' || ($this->pickedDate('from') !== null && $this->pickedDate('until') !== null)) {
                                return;
                            }

                            [$from, $to] = $this->rangeFrom(['period' => ['period' => $old]]);
                            $set('from', $from);
                            $set('until', $to);
                        })
                        ->columnSpanFull(),
                    DatePicker::make('from')
                        ->label(__('owner.reports.from'))
                        ->native(false)
                        ->displayFormat('D j M Y')
                        ->format('Y-m-d')
                        // Replaces the picker's own parsing: anything that is not a
                        // date (e.g. an edited link) is dropped instead of crashing.
                        ->afterStateHydrated(fn (DatePicker $component, mixed $state) => $component->state($this->dateOrNull($state)?->toDateString()))
                        ->closeOnDateSelection()
                        ->live()
                        ->minDate(fn () => $today()->subDays(365 * 2))
                        ->maxDate(fn () => $this->pickedDate('until') ?? $today())
                        ->visible(fn (Get $get) => $get('period') === 'custom'),
                    DatePicker::make('until')
                        ->label(__('owner.reports.until'))
                        ->native(false)
                        ->displayFormat('D j M Y')
                        ->format('Y-m-d')
                        ->afterStateHydrated(fn (DatePicker $component, mixed $state) => $component->state($this->dateOrNull($state)?->toDateString()))
                        ->closeOnDateSelection()
                        ->live()
                        ->minDate(fn () => $this->pickedDate('from'))
                        // At most MAX_DAYS from the start, and never after today.
                        ->maxDate(function (Get $get) use ($today, $span) {
                            $from = $this->pickedDate('from');

                            return $from === null ? $today() : min($from->addDays($span), $today());
                        })
                        ->helperText(__('owner.reports.range_limit', ['days' => SummariseSalesByDay::MAX_DAYS]))
                        ->visible(fn (Get $get) => $get('period') === 'custom'),
                ])
                ->indicateUsing(function (array $data): Indicator {
                    [$from, $to] = $this->rangeFrom(['period' => $data]);
                    $period = in_array($data['period'] ?? null, self::PERIODS, true) ? $data['period'] : 'today';

                    return Indicator::make(__('owner.reports.period_indicator', [
                        'period' => __("owner.reports.periods.{$period}"),
                        'range' => $this->rangeLabel($from, $to),
                    ]))->removable(false);
                }),
        ];

        $branches = array_map(fn (array $b) => $b['name'], app(ListBranches::class)->handle($this->visibleBranchIds()));

        if (count($branches) > 1) {
            $filters[] = SelectFilter::make('branch')
                ->label(__('owner.dashboard.branch'))
                ->placeholder(__('owner.dashboard.all_branches'))
                ->options($branches)
                ->native()
                ->indicateUsing(fn (array $data) => isset($branches[$data['value'] ?? ''])
                    ? __('owner.reports.branch_indicator', ['branch' => $branches[$data['value']]])
                    : null);
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
        $lastMonth = $today->startOfMonth()->subMonth();

        [$from, $to] = match ($period['period'] ?? 'today') {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'this_week' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today],
            'last_7_days' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$lastMonth, $lastMonth->endOfMonth()->startOfDay()],
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

    // Day and month names follow the app's language (Laravel keeps Carbon's locale in step).
    protected function dayLabel(string $date): string
    {
        return CarbonImmutable::parse($date)->translatedFormat('D j M Y');
    }

    /** "Fri 23 Oct 2026", "17–23 Oct 2026", "28 Sep – 4 Oct 2026" or "29 Dec 2025 – 4 Jan 2026". */
    protected function rangeLabel(string $from, string $to): string
    {
        $a = CarbonImmutable::parse($from);
        $b = CarbonImmutable::parse($to);

        return match (true) {
            $a->isSameDay($b) => $this->dayLabel($from),
            $a->isSameMonth($b) => $a->translatedFormat('j').'–'.$b->translatedFormat('j M Y'),
            $a->isSameYear($b) => $a->translatedFormat('j M').' – '.$b->translatedFormat('j M Y'),
            default => $a->translatedFormat('j M Y').' – '.$b->translatedFormat('j M Y'),
        };
    }

    /**
     * Runs before the filter form is filled: drops period values and dates
     * that are not real, so an edited link shows what is actually applied.
     */
    public function mountHasReportFilters(): void
    {
        $period = $this->tableFilters['period'] ?? null;

        if (! is_array($period)) {
            return;
        }

        if (! in_array($period['period'] ?? null, self::PERIODS, true)) {
            $period['period'] = 'today';
        }

        foreach (['from', 'until'] as $field) {
            $period[$field] = $this->dateOrNull($period[$field] ?? null)?->toDateString();
        }

        $this->tableFilters['period'] = $period;
    }

    /** A custom-range date as the browser sent it, read without Filament parsing it first. */
    private function pickedDate(string $field): ?CarbonImmutable
    {
        return $this->dateOrNull($this->tableFilters['period'][$field] ?? null);
    }

    /** A real calendar date in Y-m-d form (a time after it is ignored), or null for anything else. */
    private function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        $ymd = substr($value, 0, 10);

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $ymd);
        } catch (InvalidFormatException) {
            return null;
        }

        // Rejects dates PHP would roll over, such as 2026-02-31.
        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $ymd ? $date : null;
    }
}
