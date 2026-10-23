<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Catalogue\Actions\FindItemNames;
use App\Domain\Inventory\Actions\ListNegativeStock;
use App\Domain\Tenancy\Actions\ListBranches;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/** Items below zero in a tenant's branches: sales are never refused for stock, so they are flagged here. */
class TenantNegativeStock extends TenantDataPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function getNavigationLabel(): string
    {
        return __('admin.tenant.negative_stock');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tenant.negative_stock');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->inTenant(function () {
                $levels = app(ListNegativeStock::class)->handle();
                $items = app(FindItemNames::class)->handle(array_column($levels, 'item_id'));
                $branches = app(ListBranches::class)->handle();

                $rows = [];
                foreach ($levels as $level) {
                    $rows["{$level['branch_id']}:{$level['item_id']}"] = [
                        'branch' => $branches[$level['branch_id']]['name'] ?? $level['branch_id'],
                        'item' => $items[$level['item_id']] ?? $level['item_id'],
                        'quantity' => $level['quantity'],
                    ];
                }

                return $rows;
            }))
            ->emptyStateHeading(__('admin.negative_stock.empty'))
            ->columns([
                TextColumn::make('branch')->label(__('admin.negative_stock.branch')),
                TextColumn::make('item')->label(__('admin.negative_stock.item')),
                TextColumn::make('quantity')->label(__('admin.negative_stock.quantity'))->color('danger'),
            ]);
    }
}
