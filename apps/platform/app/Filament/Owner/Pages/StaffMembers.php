<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Actions\ListStaff;
use App\Domain\Tenancy\Actions\ResetStaffPin;
use App\Domain\Tenancy\Actions\SaveStaff;
use App\Domain\Tenancy\Actions\SetStaffActive;
use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Support\StaffRules;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The business's people: roles, branches, till PINs. Owners look after
 * everyone; managers look after cashiers and waiters in their own branches
 * (StaffRules, checked again by every action on the server). A PIN is
 * generated, shown once, and handed over; only its hash is kept.
 */
class StaffMembers extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'staff';

    public static function getNavigationLabel(): string
    {
        return __('owner.staff.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.staff.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters): array => array_column(
                app(ListStaff::class)->handle($this->staff(), ($filters['status']['value'] ?? null) !== 'inactive'),
                null,
                'id',
            ))
            ->paginated(false)
            ->description(__('owner.staff.description'))
            ->emptyStateHeading(__('owner.staff.none'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('owner.staff.status'))
                    ->options(['active' => __('owner.staff.active'), 'inactive' => __('owner.staff.inactive')])
                    ->default('active')
                    ->selectablePlaceholder(false),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('name')->label(__('owner.staff.name'))
                    ->description(fn (array $record): ?string => $record['is_you'] ? (string) __('owner.staff.you') : null),
                TextColumn::make('role')->label(__('owner.staff.role'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('owner.staff.roles.'.$state)),
                TextColumn::make('branches')->label(__('owner.staff.branches'))
                    ->state(fn (array $record): string => $record['role'] === 'owner'
                        ? __('owner.staff.all_branches')
                        : implode(', ', $record['branches'])),
                TextColumn::make('pin_set_at')->label(__('owner.staff.pin'))
                    ->state(fn (array $record): string => $record['has_pin'] && $record['pin_set_at'] !== null
                        ? __('owner.staff.pin_set', ['date' => Carbon::parse($record['pin_set_at'])->timezone((string) config('vendapos.business_timezone'))->format('j M Y')])
                        : __('owner.staff.pin_not_set')),
                IconColumn::make('panel_login')->label(__('owner.staff.panel_login'))->boolean(),
            ])
            ->headerActions([
                Action::make('addStaff')
                    ->label(__('owner.staff.add'))
                    ->visible(fn () => $this->rules()->rolesFor($this->staff()) !== [])
                    ->authorize(fn () => $this->rules()->rolesFor($this->staff()) !== [])
                    ->schema($this->staffForm())
                    ->action(function (Action $action, array $data) {
                        $id = $this->change($action, fn () => app(SaveStaff::class)->handle($this->staff(), null, $data['name'], $data['role'], $this->branchIdsFrom($data)));
                        // A new person gets their PIN straight away.
                        $this->revealPin($action, (string) $id, $data['name']);
                    }),
            ])
            ->recordActions([
                Action::make('editStaff')
                    ->label(__('owner.staff.edit'))
                    ->visible(fn (array $record) => $record['manageable'] && $record['is_active'])
                    ->authorize(fn (array $record) => $this->canManage($record['id']))
                    ->fillForm(fn (array $record) => ['name' => $record['name'], 'role' => $record['role'], 'branch_ids' => $record['branch_ids']])
                    ->schema($this->staffForm())
                    ->action(function (Action $action, array $record, array $data) {
                        $this->change($action, fn () => app(SaveStaff::class)->handle($this->staff(), $record['id'], $data['name'], $data['role'], $this->branchIdsFrom($data)));
                        Notification::make()->title(__('owner.staff.saved'))->success()->send();
                    }),
                Action::make('resetPin')
                    ->label(fn (array $record) => $record['has_pin'] ? __('owner.staff.reset_pin') : __('owner.staff.give_pin'))
                    ->requiresConfirmation()
                    ->modalDescription(__('owner.staff.reset_pin_confirm'))
                    ->visible(fn (array $record) => $record['manageable'] && $record['is_active'])
                    ->authorize(fn (array $record) => $this->canManage($record['id']))
                    ->action(fn (Action $action, array $record) => $this->revealPin($action, $record['id'], $record['name'])),
                Action::make('deactivate')
                    ->label(__('owner.staff.deactivate'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('owner.staff.deactivate_confirm'))
                    ->visible(fn (array $record) => $record['manageable'] && $record['is_active'] && ! $record['is_you'])
                    ->authorize(fn (array $record) => $this->canManage($record['id']))
                    ->action(function (Action $action, array $record) {
                        $this->change($action, fn () => app(SetStaffActive::class)->handle($this->staff(), $record['id'], false));
                        Notification::make()->title(__('owner.staff.deactivated'))->success()->send();
                    }),
                Action::make('reactivate')
                    ->label(__('owner.staff.reactivate'))
                    ->visible(fn (array $record) => $record['manageable'] && ! $record['is_active'])
                    ->authorize(fn (array $record) => $this->canManage($record['id']))
                    ->action(function (Action $action, array $record) {
                        $this->change($action, fn () => app(SetStaffActive::class)->handle($this->staff(), $record['id'], true));
                        Notification::make()->title(__('owner.staff.reactivated'))->success()->send();
                    }),
            ]);
    }

    /** Shows a new PIN once. Opened only by revealPin(), never from a button. */
    public function showPinAction(): Action
    {
        return Action::make('showPin')
            ->modalHeading(fn (array $arguments): string => __('owner.staff.pin_for', ['name' => $arguments['name'] ?? '']))
            ->modalDescription(__('owner.staff.pin_once'))
            ->modalContent(fn (array $arguments) => new HtmlString(
                '<p style="font-family:ui-monospace,monospace;font-size:2.5rem;letter-spacing:.4em;text-align:center;margin:1rem 0">'.e((string) ($arguments['pin'] ?? '')).'</p>',
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('owner.staff.done'));
    }

    private function revealPin(Action $action, string $staffId, string $name): void
    {
        $pin = $this->change($action, fn () => app(ResetStaffPin::class)->handle($this->staff(), $staffId));

        $this->replaceMountedAction('showPin', ['name' => $name, 'pin' => $pin]);
    }

    /** Runs a staff change; a refusal keeps the form open with the reason. */
    private function change(Action $action, Closure $change): mixed
    {
        $result = null;

        try {
            $result = $change();
        } catch (StaffChangeRefused $e) {
            Notification::make()->title(__('owner.staff.refused.'.$e->reason))->danger()->send();
            $action->halt();
        }

        $this->flushCachedTableRecords();

        return $result;
    }

    private function canManage(string $staffId): bool
    {
        $target = Staff::query()->find($staffId);

        return $target instanceof Staff && $this->rules()->canManage($this->staff(), $target);
    }

    private function rules(): StaffRules
    {
        return app(StaffRules::class);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function branchIdsFrom(array $data): array
    {
        $ids = $data['branch_ids'] ?? [];

        return is_array($ids) ? array_values(array_map(strval(...), $ids)) : [];
    }

    /** @return list<TextInput|Select|CheckboxList> */
    private function staffForm(): array
    {
        $roles = [];
        foreach ($this->rules()->rolesFor($this->staff()) as $role) {
            $roles[$role] = __('owner.staff.roles.'.$role);
        }

        $branches = array_column(app(ListBranches::class)->handle($this->rules()->branchIdsFor($this->staff())), 'name', 'id');

        return [
            TextInput::make('name')->label(__('owner.staff.name'))->required()->maxLength(80),
            Select::make('role')->label(__('owner.staff.role'))
                ->options($roles)
                ->default(array_key_last($roles))
                ->helperText(__('owner.staff.role_help'))
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            CheckboxList::make('branch_ids')->label(__('owner.staff.branches'))
                ->options($branches)
                ->default(count($branches) === 1 ? array_keys($branches) : [])
                ->hidden(fn (Get $get) => $get('role') === 'owner')
                ->required(fn (Get $get) => $get('role') !== 'owner'),
        ];
    }
}
