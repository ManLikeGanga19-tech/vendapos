<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Tenancy\Actions\ListBranches;
use App\Domain\Tenancy\Models\Device;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;

/** A tenant's enrolled terminals (central table), with revoke. */
class TenantDevices extends TenantDataPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-tablet';

    /** @var array<string, string>|null branch ID => name, read once per request */
    private ?array $branchNames = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.tenant.devices');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tenant.devices');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Device::query()
                ->where('tenant_id', $this->tenant()->getTenantKey())
                ->withMax('tokens', 'expires_at'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('admin.devices.name'))->searchable(),
                TextColumn::make('branch_id')->label(__('admin.devices.branch'))
                    ->formatStateUsing(fn (string $state) => $this->branchNames()[$state] ?? $state),
                TextColumn::make('last_seen_at')->label(__('admin.devices.last_seen'))->since()->placeholder(__('admin.devices.never')),
                TextColumn::make('tokens_max_expires_at')->label(__('admin.devices.token_expires'))->dateTime()->placeholder('—'),
                TextColumn::make('status')->label(__('admin.devices.status'))
                    ->state(fn (Device $record) => $record->isRevoked() ? __('admin.devices.revoked') : __('admin.devices.active')),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('admin.devices.revoke'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.devices.revoke_confirm'))
                    ->visible(fn (Device $record) => ! $record->isRevoked())
                    ->authorize(fn (Device $record) => static::isStaff() && $record->tenant_id === $this->tenant()->getTenantKey())
                    ->action(function (Device $record) {
                        $record->revoke();
                        $this->flushCachedTableRecords();
                        Log::info('devices.revoked', ['tenant_id' => $record->tenant_id, 'device_id' => $record->id, 'revoked_by' => $this->staffEmail()]);
                        Notification::make()->title(__('admin.devices.revoked_notice'))->success()->send();
                    }),
            ]);
    }

    /** @return array<string, string> */
    private function branchNames(): array
    {
        return $this->branchNames ??= array_map(
            fn (array $branch) => $branch['name'],
            $this->inTenant(fn () => app(ListBranches::class)->handle()),
        );
    }
}
