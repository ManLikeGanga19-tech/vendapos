<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Tenancy\Actions\ListBranches;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/** Today at a glance, for every branch the person may see or one of them. */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    use ScopesToStaffBranches;

    public function filtersForm(Schema $schema): Schema
    {
        $branches = array_map(
            fn (array $branch) => $branch['name'],
            app(ListBranches::class)->handle($this->visibleBranchIds()),
        );

        return $schema->components([
            Select::make('branch')
                ->label(__('owner.dashboard.branch'))
                ->placeholder(__('owner.dashboard.all_branches'))
                ->options($branches)
                // Nothing to choose between with a single branch.
                ->visible(count($branches) > 1),
        ]);
    }
}
