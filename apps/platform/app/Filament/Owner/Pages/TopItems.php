<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Analytics\Actions\CountSoldItems;
use App\Domain\Analytics\Actions\ListTopItems;
use App\Domain\Analytics\Actions\SummariseSalesPeriod;
use App\Domain\Catalogue\Actions\FindItemIdsByName;
use App\Domain\Catalogue\Actions\FindItemNames;
use App\Filament\Owner\Concerns\HasReportFilters;
use BackedEnum;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use UnitEnum;
use VendaPOS\Core\ValueObjects\Money;

/**
 * Every item sold over a period, by revenue or quantity, net of voids, with
 * each one's share of all sales in the period. Paginated on the server: the
 * database returns one page and a count. Searching by name happens in the
 * database too, so an item is found wherever it ranks.
 */
class TopItems extends Page implements HasTable
{
    use HasReportFilters;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reports/top-items';

    /** @var array{items: int, total_cents: int, from: string, to: string}|null */
    private ?array $summary = null;

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
        $rankBy = Filter::make('rank')
            ->schema([
                ToggleButtons::make('by')
                    ->label(__('owner.reports.rank_by'))
                    ->options([
                        ListTopItems::BY_REVENUE => __('owner.dashboard.revenue'),
                        ListTopItems::BY_QUANTITY => __('owner.reports.quantity_sold'),
                    ])
                    ->default(ListTopItems::BY_REVENUE)
                    // A shared link may leave the ranking out; show what is applied.
                    ->formatStateUsing(fn (?string $state): string => $state === ListTopItems::BY_QUANTITY ? $state : ListTopItems::BY_REVENUE)
                    ->grouped()
                    ->live(),
            ])
            ->indicateUsing(fn (array $data) => ($data['by'] ?? null) === ListTopItems::BY_QUANTITY
                ? __('owner.reports.ranked_by_quantity')
                : null);

        return $this->withReportFilters($table, $rankBy)
            ->records(fn (?array $filters, ?string $search, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->rows($filters, $search, (int) $page, (int) $recordsPerPage))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->searchable()
            ->searchPlaceholder(__('owner.reports.search_items'))
            ->description(fn (): ?string => $this->summaryLine())
            ->emptyStateHeading(fn (): string => filled($this->getTableSearch())
                ? __('owner.reports.no_match', ['search' => $this->getTableSearch()])
                : __('owner.reports.no_sales'))
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

    /**
     * @param  array<string, mixed>|null  $filters
     * @return LengthAwarePaginator<string, array{rank: int, item: string, quantity: int, total_cents: int, share: float}>
     */
    private function rows(?array $filters, ?string $search, int $page, int $perPage): LengthAwarePaginator
    {
        [$from, $to] = $this->rangeFrom($filters);
        $branchIds = $this->reportBranchIds($filters);
        $rankBy = ($filters['rank']['by'] ?? null) === ListTopItems::BY_QUANTITY ? ListTopItems::BY_QUANTITY : ListTopItems::BY_REVENUE;
        $itemIds = filled($search) ? app(FindItemIdsByName::class)->handle((string) $search) : null;
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $count = app(CountSoldItems::class)->handle($from, $to, $branchIds, $itemIds);
        $top = app(ListTopItems::class)->handle($from, $to, $branchIds, $perPage, $rankBy, $itemIds, $offset);
        $names = app(FindItemNames::class)->handle(array_column($top, 'item_id'));
        // Share of all sales in the period, not just of the items listed.
        $periodTotal = app(SummariseSalesPeriod::class)->handle($from, $to, $branchIds)['total_cents'];

        $this->summary = ['items' => $count, 'total_cents' => $periodTotal, 'from' => $from, 'to' => $to];

        $rows = [];
        foreach ($top as $i => $row) {
            $rows[$row['item_id']] = [
                'rank' => $offset + $i + 1,
                'item' => $names[$row['item_id']] ?? (string) __('owner.dashboard.unknown_item'),
                'quantity' => $row['quantity'],
                'total_cents' => $row['total_cents'],
                'share' => round($row['total_cents'] * 100 / max(1, $periodTotal), 1),
            ];
        }

        return new LengthAwarePaginator($rows, $count, $perPage, $page);
    }

    private function summaryLine(): ?string
    {
        if ($this->summary === null) {
            $this->rows($this->tableFilters, $this->getTableSearch(), (int) $this->getTablePage(), (int) $this->getTableRecordsPerPage());
        }

        $summary = $this->summary;

        return $summary === null ? null : trans_choice('owner.reports.top_items_summary', $summary['items'], [
            'count' => $summary['items'],
            'total' => Money::fromCents($summary['total_cents'])->format(),
            'range' => $this->rangeLabel($summary['from'], $summary['to']),
        ]);
    }
}
