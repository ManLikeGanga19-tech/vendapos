<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Domain\Analytics\Actions\ListTopItems;
use App\Domain\Catalogue\Actions\FindItemNames;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use VendaPOS\Core\ValueObjects\Money;

/** Today's five best sellers by revenue, net of voids. */
class TopItemsToday extends TableWidget
{
    use InteractsWithPageFilters;
    use ScopesToStaffBranches;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('owner.dashboard.top_items'))
            ->records(function (): array {
                $today = $this->businessToday();
                $top = app(ListTopItems::class)->handle($today, $today, $this->selectedBranchIds());
                $names = app(FindItemNames::class)->handle(array_column($top, 'item_id'));

                $rows = [];
                foreach ($top as $row) {
                    $rows[$row['item_id']] = [
                        'item' => $names[$row['item_id']] ?? __('owner.dashboard.unknown_item'),
                        'quantity' => $row['quantity'],
                        'total' => Money::fromCents($row['total_cents'])->format(),
                    ];
                }

                return $rows;
            })
            ->paginated(false)
            ->emptyStateHeading(__('owner.dashboard.no_sales'))
            ->columns([
                TextColumn::make('item')->label(__('owner.dashboard.item')),
                TextColumn::make('quantity')->label(__('owner.dashboard.quantity'))->alignEnd(),
                TextColumn::make('total')->label(__('owner.dashboard.revenue'))->alignEnd(),
            ]);
    }
}
