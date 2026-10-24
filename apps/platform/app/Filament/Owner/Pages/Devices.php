<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Tenancy\Actions\IssuePairingCode;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Concerns\ScopesToStaffBranches;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * The tills in the branches this person looks after: pair a new one with a
 * one-time code, or revoke one that is lost or replaced.
 */
class Devices extends Page implements HasTable
{
    use InteractsWithTable;
    use ScopesToStaffBranches;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-tablet';

    protected static ?int $navigationSort = 21;

    protected static ?string $slug = 'devices';

    /** @var array<string, string>|null branch ID => name, read once per request */
    private ?array $branchNames = null;

    public static function getNavigationLabel(): string
    {
        return __('owner.devices.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner.devices.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Device::query()->ofTenantBranches($this->tenant()->getTenantKey(), $this->visibleBranchIds()))
            ->defaultSort('name')
            ->paginated(false)
            ->emptyStateHeading(__('owner.devices.none'))
            ->emptyStateDescription(__('owner.devices.none_help'))
            ->columns([
                TextColumn::make('name')->label(__('owner.devices.name')),
                TextColumn::make('branch_id')->label(__('owner.devices.branch'))
                    ->formatStateUsing(fn (string $state) => $this->branchNames()[$state] ?? '—'),
                TextColumn::make('last_seen_at')->label(__('owner.devices.last_seen'))->since()->placeholder(__('owner.devices.never')),
                TextColumn::make('status')->label(__('owner.devices.status'))
                    ->state(fn (Device $record) => $record->isRevoked() ? __('owner.devices.revoked') : __('owner.devices.active')),
            ])
            ->headerActions([
                Action::make('pairTill')
                    ->label(__('owner.devices.pair'))
                    ->visible(fn () => $this->branchNames() !== [])
                    ->schema([
                        Select::make('branch_id')->label(__('owner.devices.branch'))
                            ->options(fn () => $this->branchNames())
                            ->default(fn () => count($this->branchNames()) === 1 ? array_key_first($this->branchNames()) : null)
                            ->required(),
                        TextInput::make('name')->label(__('owner.devices.name'))
                            ->placeholder(__('owner.devices.name_example'))
                            ->required()
                            ->maxLength(60),
                    ])
                    ->action(function (Action $action, array $data) {
                        $code = '';

                        try {
                            $code = app(IssuePairingCode::class)->handle($this->tenant(), $this->staff(), (string) $data['branch_id'], (string) $data['name']);
                        } catch (StaffChangeRefused) {
                            Notification::make()->title(__('owner.devices.branch_not_yours'))->danger()->send();
                            $action->halt();
                        }

                        $this->replaceMountedAction('showPairingCode', ['code' => IssuePairingCode::format($code), 'name' => $data['name']]);
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('owner.devices.revoke'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('owner.devices.revoke_confirm'))
                    ->visible(fn (Device $record) => ! $record->isRevoked())
                    ->authorize(fn (Device $record) => $record->tenant_id === $this->tenant()->getTenantKey()
                        && in_array($record->branch_id, $this->visibleBranchIds(), true))
                    ->action(function (Device $record) {
                        $record->revoke();
                        $this->flushCachedTableRecords();
                        Log::channel('security')->info('owner.device_revoked', ['tenant_id' => $record->tenant_id, 'device_id' => $record->id, 'staff_id' => $this->staff()->id]);
                        Notification::make()->title(__('owner.devices.revoked_notice'))->success()->send();
                    }),
            ]);
    }

    /** Shows a new pairing code once. Opened only after pairTill, never from a button. */
    public function showPairingCodeAction(): Action
    {
        return Action::make('showPairingCode')
            ->modalHeading(fn (array $arguments): string => __('owner.devices.code_for', ['name' => $arguments['name'] ?? '']))
            ->modalDescription(__('owner.devices.code_help', ['minutes' => (int) config('vendapos.pairing_code_minutes')]))
            ->modalContent(fn (array $arguments) => new HtmlString(
                '<p style="font-family:ui-monospace,monospace;font-size:2.25rem;letter-spacing:.25em;text-align:center;margin:1rem 0">'.e((string) ($arguments['code'] ?? '')).'</p>',
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('owner.staff.done'));
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_column(app(ListBranches::class)->handle($this->visibleBranchIds()), 'name', 'id');
    }
}
