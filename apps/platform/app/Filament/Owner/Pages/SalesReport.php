<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Filament\Owner\Concerns\HasReportFilters;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;
use VendaPOS\Core\ValueObjects\Money;

/** Sales for each business day in a period, from the hourly rollup. */
class SalesReport extends Page implements HasTable
{
    use HasReportFilters;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'reports/sales';

    /** @var array<string, array{date: string, orders: int, voids: int, cancels: int, subtotal_cents: int, vat_cents: int, total_cents: int}>|null */
    private ?array $days = null;

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

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters): array => $this->days($filters))
            ->description(fn (): string => $this->totalsLine())
            ->filters($this->reportFilters(), layout: FiltersLayout::AboveContent)
            ->paginated(false)
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
     * @param  array<string, mixed>|null  $filters
     * @return array<string, array{date: string, orders: int, voids: int, cancels: int, subtotal_cents: int, vat_cents: int, total_cents: int}>
     */
    private function days(?array $filters): array
    {
        [$from, $to] = $this->rangeFrom($filters);

        return $this->days = app(SummariseSalesByDay::class)->handle($from, $to, $this->reportBranchIds($filters));
    }

    private function totalsLine(): string
    {
        $days = $this->days ?? $this->days($this->tableFilters);
        $sum = fn (string $key): int => (int) array_sum(array_column($days, $key));

        return __('owner.reports.totals', [
            'total' => Money::fromCents($sum('total_cents'))->format(),
            'vat' => Money::fromCents($sum('vat_cents'))->format(),
            'orders' => $sum('orders'),
        ]);
    }
}
