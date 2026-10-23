<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Analytics\Actions\ListTopItems;
use App\Domain\Analytics\Actions\SummariseSalesByDay;
use App\Domain\Catalogue\Actions\FindItemNames;
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

/** Best sellers by revenue over a period, net of voids, with each one's share of sales. */
class TopItems extends Page implements HasTable
{
    use HasReportFilters;
    use InteractsWithTable;

    public const int LIMIT = 50;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reports/top-items';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('owner.reports.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.reports.top_items');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.reports.top_items');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?array $filters): array {
                [$from, $to] = $this->rangeFrom($filters);
                $branchIds = $this->reportBranchIds($filters);
                $top = app(ListTopItems::class)->handle($from, $to, $branchIds, self::LIMIT);
                $names = app(FindItemNames::class)->handle(array_column($top, 'item_id'));
                // Share of all sales in the period, not just of the items listed.
                $all = max(1, array_sum(array_column(app(SummariseSalesByDay::class)->handle($from, $to, $branchIds), 'total_cents')));

                $rows = [];
                foreach ($top as $rank => $row) {
                    $rows[$row['item_id']] = [
                        'rank' => $rank + 1,
                        'item' => $names[$row['item_id']] ?? __('owner.dashboard.unknown_item'),
                        'quantity' => $row['quantity'],
                        'total_cents' => $row['total_cents'],
                        'share' => round($row['total_cents'] * 100 / $all, 1),
                    ];
                }

                return $rows;
            })
            ->filters($this->reportFilters(), layout: FiltersLayout::AboveContent)
            ->paginated(false)
            ->emptyStateHeading(__('owner.reports.no_sales'))
            ->columns([
                TextColumn::make('rank')->label('#'),
                TextColumn::make('item')->label(__('owner.dashboard.item')),
                TextColumn::make('quantity')->label(__('owner.dashboard.quantity'))->alignEnd(),
                TextColumn::make('total_cents')->label(__('owner.dashboard.revenue'))->alignEnd()
                    ->formatStateUsing(fn (int $state) => Money::fromCents($state)->format()),
                TextColumn::make('share')->label(__('owner.reports.share'))->alignEnd()
                    ->formatStateUsing(fn (float $state) => number_format($state, 1).'%'),
            ]);
    }
}
