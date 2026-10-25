<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Domain\Analytics\Actions\SummariseSalesPeriod;
use App\Filament\Owner\Concerns\HasReportFilters;
use App\Filament\Owner\Reports\Widgets\BusiestHours;
use App\Filament\Owner\Reports\Widgets\SalesKpis;
use App\Filament\Owner\Reports\Widgets\SalesPerDay;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use UnitEnum;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Sales for each business day in a period, from the hourly rollup, with the
 * period's headline figures and charts above. The table is paginated on the
 * server: each page reads only its own days.
 */
class SalesReport extends Page implements HasTable
{
    use HasReportFilters;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'reports/sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('owner.reports.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.reports.sales');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.reports.sales');
    }

    /** @return list<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [SalesKpis::class, SalesPerDay::class, BusiestHours::class];
    }

    /** The widgets follow the table's filters. */
    public function getWidgetData(): array
    {
        return ['tableFilters' => $this->tableFilters];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $this->withReportFilters($table)
            ->records(fn (?array $filters, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->days($filters, (int) $page, (int) $recordsPerPage))
            // The whole period's totals, since the rows below are one page of it.
            ->description(fn (): string => $this->totalsLine())
            ->paginated([7, 14, 31])
            ->defaultPaginationPageOption(14)
            ->columns([
                TextColumn::make('date')->label(__('owner.reports.date'))
                    ->formatStateUsing(fn (string $state) => $this->dayLabel($state)),
                TextColumn::make('orders')->label(__('owner.dashboard.orders'))->alignEnd(),
                TextColumn::make('voids')->label(__('owner.reports.voids'))->alignEnd(),
                TextColumn::make('cancels')->label(__('owner.reports.cancels'))->alignEnd(),
                TextColumn::make('subtotal_cents')->label(__('owner.reports.excl_vat'))->alignEnd()
                    ->formatStateUsing(fn (int $state) => Money::fromCents($state)->format()),
                TextColumn::make('vat_cents')->label(__('owner.reports.vat'))->alignEnd()
                    ->formatStateUsing(fn (int $state) => Money::fromCents($state)->format()),
                TextColumn::make('total_cents')->label(__('owner.reports.total'))->alignEnd()->weight('bold')
                    ->formatStateUsing(fn (int $state) => Money::fromCents($state)->format()),
            ]);
    }

    /**
     * One page of days, newest first. Only that page's dates are read.
     *
     * @param  array<string, mixed>|null  $filters
     * @return LengthAwarePaginator<string, array{date: string, orders: int, voids: int, cancels: int, subtotal_cents: int, vat_cents: int, total_cents: int}>
     */
    private function days(?array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        [$from, $to] = $this->rangeFrom($filters);
        $first = CarbonImmutable::parse($from);
        $last = CarbonImmutable::parse($to);
        $dayCount = (int) $first->diffInDays($last) + 1;
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $skip = ($page - 1) * $perPage;
        $days = [];

        if ($skip < $dayCount) {
            $pageTo = $last->subDays($skip);
            $pageFrom = $pageTo->subDays($perPage - 1)->max($first);
            $days = app(SummariseSalesByDay::class)->handle($pageFrom->toDateString(), $pageTo->toDateString(), $this->reportBranchIds($filters));
        }

        return new LengthAwarePaginator($days, $dayCount, $perPage, $page);
    }

    private function totalsLine(): string
    {
        [$from, $to] = $this->rangeFrom($this->tableFilters);
        $period = app(SummariseSalesPeriod::class)->handle($from, $to, $this->reportBranchIds($this->tableFilters));

        return __('owner.reports.totals', [
            'total' => Money::fromCents($period['total_cents'])->format(),
            'vat' => Money::fromCents($period['vat_cents'])->format(),
            'orders' => number_format($period['orders']),
        ]);
    }
}
