<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Verticals\Actions\ManageFloor;
use App\Domain\Verticals\Exceptions\FloorChangeRefused;
use App\Domain\Verticals\Models\DiningTable;
use App\Domain\Verticals\Models\FloorArea;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\TerminalMode;

/**
 * The floor plan of each branch that serves at tables: areas (Main hall,
 * Terrace) and their tables, which the tills show as a grid. Owners manage
 * every branch, managers their own. Tables are switched off, never deleted.
 */
class Tables extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    /** Branch modes whose tills seat guests at tables. */
    private const array SEATED_MODES = [TerminalMode::TableService, TerminalMode::Tabs];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'tables';

    /** @var array<string, string>|null branch ID => name, read once per request */
    private ?array $branchNames = null;

    /** Only for people who look after at least one restaurant, hotel kitchen or bar. */
    public static function canAccess(): bool
    {
        $staff = Filament::auth()->user();

        return $staff instanceof Staff && self::seatedBranches($staff->visibleBranchIds()) !== [];
    }

    public static function getNavigationLabel(): string
    {
        return __('owner.tables.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.tables.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DiningTable::query()->with('area')->whereIn('branch_id', array_keys($this->branchNames())))
            ->defaultSort('sort_order')
            ->paginated(false)
            ->description(__('owner.tables.description'))
            ->emptyStateHeading(__('owner.tables.none'))
            ->emptyStateDescription(__('owner.tables.none_help'))
            ->defaultGroup(Group::make('floor_area_id')
                ->label(__('owner.tables.area'))
                ->getTitleFromRecordUsing(fn (DiningTable $record): string => $this->areaTitle($record)))
            ->filters([
                SelectFilter::make('branch_id')->label(__('owner.tables.branch'))->options(fn () => $this->branchNames()),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('name')->label(__('owner.tables.name'))->weight('bold'),
                TextColumn::make('seats')->label(__('owner.tables.seats')),
                TextColumn::make('is_active')->label(__('owner.tables.status'))
                    ->formatStateUsing(fn (bool $state): string => $state ? __('owner.tables.in_use') : __('owner.tables.off')),
            ])
            ->headerActions([
                Action::make('addArea')
                    ->label(__('owner.tables.add_area'))
                    ->schema([
                        $this->branchSelect(),
                        TextInput::make('name')->label(__('owner.tables.area_name'))->placeholder(__('owner.tables.area_example'))->required()->maxLength(60),
                    ])
                    ->action(function (Action $action, array $data) {
                        $this->change($action, fn () => app(ManageFloor::class)->saveArea(null, $this->ownBranch((string) $data['branch_id']), (string) $data['name']));
                    }),
                Action::make('addTables')
                    ->label(__('owner.tables.add_tables'))
                    ->visible(fn () => $this->areaOptions() !== [])
                    ->schema([
                        Select::make('floor_area_id')->label(__('owner.tables.area'))->options(fn () => $this->areaOptions())->required(),
                        TextInput::make('names')->label(__('owner.tables.names'))
                            ->helperText(__('owner.tables.names_help', ['max' => ManageFloor::MAX_TABLES_AT_ONCE]))
                            ->placeholder('1-12')->required()->maxLength(500),
                        TextInput::make('seats')->label(__('owner.tables.seats'))->numeric()->minValue(1)->maxValue(99)->default(4)->required(),
                    ])
                    ->action(function (Action $action, array $data) {
                        $this->change($action, fn () => app(ManageFloor::class)->addTables($this->ownArea((string) $data['floor_area_id']), (string) $data['names'], (int) $data['seats']));
                    }),
                Action::make('renameArea')
                    ->label(__('owner.tables.rename_area'))
                    ->visible(fn () => $this->areaOptions() !== [])
                    ->schema([
                        Select::make('floor_area_id')->label(__('owner.tables.area'))->options(fn () => $this->areaOptions())->required(),
                        TextInput::make('name')->label(__('owner.tables.area_name'))->required()->maxLength(60),
                    ])
                    ->action(function (Action $action, array $data) {
                        $area = FloorArea::query()->findOrFail($this->ownArea((string) $data['floor_area_id']));
                        $this->change($action, fn () => app(ManageFloor::class)->saveArea($area->id, $area->branch_id, (string) $data['name']));
                    }),
                Action::make('removeArea')
                    ->label(__('owner.tables.remove_area'))
                    ->color('danger')
                    ->visible(fn () => $this->areaOptions() !== [])
                    ->modalDescription(__('owner.tables.remove_area_help'))
                    ->schema([
                        Select::make('floor_area_id')->label(__('owner.tables.area'))->options(fn () => $this->areaOptions())->required(),
                    ])
                    ->action(function (Action $action, array $data) {
                        $this->change($action, fn () => app(ManageFloor::class)->deleteArea($this->ownArea((string) $data['floor_area_id'])));
                    }),
            ])
            ->recordActions([
                Action::make('editTable')
                    ->label(__('owner.tables.edit'))
                    ->authorize(fn (DiningTable $record) => isset($this->branchNames()[$record->branch_id]))
                    ->fillForm(fn (DiningTable $record) => ['name' => $record->name, 'seats' => $record->seats, 'floor_area_id' => $record->floor_area_id])
                    ->schema(fn (DiningTable $record) => [
                        TextInput::make('name')->label(__('owner.tables.name'))->required()->maxLength(20),
                        TextInput::make('seats')->label(__('owner.tables.seats'))->numeric()->minValue(1)->maxValue(99)->required(),
                        Select::make('floor_area_id')->label(__('owner.tables.area'))
                            ->options(fn () => FloorArea::query()->where('branch_id', $record->branch_id)->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->required(),
                    ])
                    ->action(function (Action $action, DiningTable $record, array $data) {
                        $this->change($action, fn () => app(ManageFloor::class)->updateTable($record->id, (string) $data['name'], (int) $data['seats'], (string) $data['floor_area_id']));
                    }),
                Action::make('toggleTable')
                    ->label(fn (DiningTable $record) => $record->is_active ? __('owner.tables.switch_off') : __('owner.tables.switch_on'))
                    ->color(fn (DiningTable $record) => $record->is_active ? 'danger' : 'gray')
                    ->authorize(fn (DiningTable $record) => isset($this->branchNames()[$record->branch_id]))
                    ->action(function (Action $action, DiningTable $record) {
                        $this->change($action, fn () => app(ManageFloor::class)->setTableActive($record->id, ! $record->is_active));
                    }),
            ]);
    }

    /** Runs a floor change; a refusal keeps the form open with the reason. */
    private function change(Action $action, Closure $change): void
    {
        try {
            $change();
        } catch (FloorChangeRefused $e) {
            Notification::make()->title(__('owner.tables.refused.'.$e->reason))->danger()->send();
            $action->halt();
        }

        $this->flushCachedTableRecords();
        Notification::make()->title(__('owner.tables.saved'))->success()->send();
    }

    private function branchSelect(): Select
    {
        return Select::make('branch_id')->label(__('owner.tables.branch'))
            ->options(fn () => $this->branchNames())
            ->default(fn () => count($this->branchNames()) === 1 ? array_key_first($this->branchNames()) : null)
            ->required();
    }

    /** A branch ID from the browser, accepted only if it is one this person looks after. */
    private function ownBranch(string $branchId): string
    {
        abort_unless(isset($this->branchNames()[$branchId]), 403);

        return $branchId;
    }

    private function ownArea(string $areaId): string
    {
        abort_unless(isset($this->areaOptions()[$areaId]), 403);

        return $areaId;
    }

    /** @return array<string, string> area ID => "Branch · Area" */
    private function areaOptions(): array
    {
        $branches = $this->branchNames();

        return FloorArea::query()->whereIn('branch_id', array_keys($branches))->orderBy('sort_order')->get()
            ->mapWithKeys(fn (FloorArea $area) => [$area->id => count($branches) > 1 ? "{$branches[$area->branch_id]} · {$area->name}" : $area->name])
            ->all();
    }

    private function areaTitle(DiningTable $table): string
    {
        $branches = $this->branchNames();
        $area = $table->area->name ?? '—';

        return count($branches) > 1 ? ($branches[$table->branch_id] ?? '—')." · {$area}" : $area;
    }

    /** @return array<string, string> branch ID => name, for this person's restaurant and bar branches */
    private function branchNames(): array
    {
        return $this->branchNames ??= self::seatedBranches($this->visibleBranchIds());
    }

    /**
     * @param  list<string>  $branchIds
     * @return array<string, string>
     */
    private static function seatedBranches(array $branchIds): array
    {
        $seated = array_filter(
            app(ListBranches::class)->handle($branchIds),
            fn (array $branch) => in_array(BusinessType::tryFrom($branch['business_type'])?->terminalMode(), self::SEATED_MODES, true),
        );

        return array_column($seated, 'name', 'id');
    }
}
