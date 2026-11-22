<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Etims\Actions\SwitchBranchEtims;
use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Actions\SetEtimsBranchId;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use VendaPOS\Core\Enums\BusinessType;

/** A tenant's branches: opening one with one of its modules, KRA branch ID and the per-branch eTIMS switch. */
class TenantBranches extends TenantDataPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    public static function getNavigationLabel(): string
    {
        return __('admin.tenant.branches');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tenant.branches');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->inTenant(function () {
                $switch = app(SwitchBranchEtims::class);

                return array_map(
                    fn (array $branch) => [...$branch, 'etims_enabled' => $switch->isOn($branch['id'])],
                    app(ListBranches::class)->handle(),
                );
            }))
            ->columns([
                TextColumn::make('name')->label(__('admin.branches.name')),
                TextColumn::make('business_type')->label(__('admin.branches.business_type'))
                    ->formatStateUsing(fn (string $state): string => TenantResource::moduleOptions()[$state] ?? $state),
                TextColumn::make('etims_branch_id')->label(__('admin.branches.etims_branch_id'))->placeholder('—'),
                IconColumn::make('etims_enabled')->label(__('admin.branches.etims_enabled'))->boolean(),
            ])
            ->headerActions([
                Action::make('addBranch')
                    ->label(__('admin.branches.add'))
                    ->authorize(fn () => static::isStaff())
                    ->schema([
                        TextInput::make('name')->label(__('admin.modules.branch_name'))->required()->maxLength(CreateBranch::NAME_MAX),
                        Select::make('business_type')
                            ->label(__('admin.modules.branch_type'))
                            ->helperText(__('admin.branches.add_help'))
                            ->options(fn (): array => $this->tenantModules())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->inTenant(fn () => app(CreateBranch::class)->handle((string) $data['name'], BusinessType::from((string) $data['business_type'])));
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('admin.branches.added'))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('setEtimsBranchId')
                    ->label(__('admin.branches.set_etims_branch_id'))
                    ->authorize(fn () => static::isStaff())
                    ->fillForm(fn (array $record) => ['etims_branch_id' => $record['etims_branch_id']])
                    ->schema([
                        TextInput::make('etims_branch_id')
                            ->label(__('admin.branches.etims_branch_id'))
                            ->helperText(__('admin.branches.etims_branch_id_help'))
                            ->alphaNum()
                            ->maxLength(8),
                    ])
                    ->action(function (array $record, array $data) {
                        $this->inTenant(fn () => app(SetEtimsBranchId::class)->handle($record['id'], $data['etims_branch_id'] ?? null));
                        $this->flushCachedTableRecords();
                        Notification::make()->title(__('admin.branches.saved'))->success()->send();
                    }),
                Action::make('switchEtimsOn')
                    ->label(__('admin.branches.switch_etims_on'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.branches.switch_etims_on_confirm'))
                    ->visible(fn (array $record) => ! $record['etims_enabled'])
                    ->authorize(fn () => static::isStaff())
                    ->action(fn (array $record) => $this->switchEtims($record['id'], true)),
                Action::make('switchEtimsOff')
                    ->label(__('admin.branches.switch_etims_off'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.branches.switch_etims_off_confirm'))
                    ->visible(fn (array $record) => $record['etims_enabled'])
                    ->authorize(fn () => static::isStaff())
                    ->action(fn (array $record) => $this->switchEtims($record['id'], false)),
            ]);
    }

    /** @return array<string, string> the tenant's modules that are built, to open a branch with */
    private function tenantModules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();
        $options = [];

        foreach ($tenant->moduleTypes() as $type) {
            if ($type->isAvailable()) {
                $options[$type->value] = TenantResource::moduleOptions()[$type->value];
            }
        }

        return $options;
    }

    private function switchEtims(string $branchId, bool $on): void
    {
        try {
            $this->inTenant(fn () => app(SwitchBranchEtims::class)->handle($branchId, $on));
            // Rows are cached for the request; re-read them so the switch shows its new state.
            $this->flushCachedTableRecords();
        } catch (DomainException $e) {
            Notification::make()->title(__('admin.errors.action_failed'))->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('admin.branches.saved'))->success()->send();
    }
}
