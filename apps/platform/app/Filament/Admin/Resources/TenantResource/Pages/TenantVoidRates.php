<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Orders\Actions\ListHighVoidRates;
use App\Domain\Tenancy\Actions\FindStaffNames;
use App\Domain\Tenancy\Actions\ListBranches;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Staff who voided more than the limit share of their sales in a day
 * (architecture §10 anomaly rule). The owner checks each one in their panel;
 * this is the platform's view of the same flags.
 */
class TenantVoidRates extends TenantDataPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    public static function getNavigationLabel(): string
    {
        return __('admin.tenant.void_rates');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tenant.void_rates');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->inTenant(function () {
                $flags = app(ListHighVoidRates::class)->handle();
                $branches = app(ListBranches::class)->handle();
                $staff = app(FindStaffNames::class)->handle(array_values(array_filter(array_column($flags, 'staff_id'))));

                $rows = [];
                foreach ($flags as $flag) {
                    $rows[(string) $flag['id']] = [
                        'day' => $flag['business_date'] ?? '—',
                        'branch' => $branches[$flag['branch_id']]['name'] ?? ($flag['branch_id'] ?? '—'),
                        'staff' => $staff[$flag['staff_id']] ?? ($flag['staff_id'] ?? '—'),
                        'voided' => "{$flag['voided']} / {$flag['sales']} ({$flag['percent']}%)",
                        'status' => __("admin.void_rates.{$flag['status']}"),
                    ];
                }

                return $rows;
            }))
            ->emptyStateHeading(__('admin.void_rates.empty'))
            ->columns([
                TextColumn::make('day')->label(__('admin.void_rates.day')),
                TextColumn::make('branch')->label(__('admin.void_rates.branch')),
                TextColumn::make('staff')->label(__('admin.void_rates.staff')),
                TextColumn::make('voided')->label(__('admin.void_rates.voided'))->color('danger'),
                TextColumn::make('status')->label(__('admin.void_rates.status')),
            ]);
    }
}
