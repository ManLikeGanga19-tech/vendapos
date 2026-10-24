<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Tenancy\Jobs\ExportTenantData;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Number;

/** A tenant's encrypted data exports (central table): start one, see each one's checks, download. */
class TenantExports extends TenantDataPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    /** Exports outlive the tenant's data: this list reads only the central table. */
    public static function availableAfterPurge(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.tenant.exports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tenant.exports');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => TenantExport::query()->where('tenant_id', $this->tenant()->getTenantKey()))
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.exports.taken'))->dateTime(),
                TextColumn::make('reason')->label(__('admin.exports.reason'))
                    ->formatStateUsing(fn (?string $state) => __('admin.exports.reasons.'.($state ?? TenantExport::MANUAL))),
                TextColumn::make('status')->label(__('admin.exports.status'))->badge()
                    ->formatStateUsing(fn (string $state) => __("admin.exports.statuses.{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        TenantExport::VERIFIED => 'success',
                        TenantExport::FAILED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (TenantExport $record) => $record->error),
                TextColumn::make('size_bytes')->label(__('admin.exports.size'))
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : Number::fileSize($state, precision: 1)),
                TextColumn::make('table_counts')->label(__('admin.exports.rows'))
                    ->state(fn (TenantExport $record) => $record->table_counts === null ? null : array_sum($record->table_counts))
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('expires_at')->label(__('admin.exports.kept_until'))->date()->placeholder('—'),
            ])
            ->headerActions([
                Action::make('exportNow')
                    ->label(__('admin.exports.export_now'))
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn () => $this->tenant()->status !== Tenant::PURGED)
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.exports.export_confirm'))
                    ->authorize(fn () => static::isStaff())
                    ->action(function () {
                        $user = auth()->user();
                        ExportTenantData::dispatch((string) $this->tenant()->getTenantKey(), $user === null ? null : (int) $user->getAuthIdentifier());
                        Notification::make()->title(__('admin.exports.queued'))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('admin.exports.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (TenantExport $record) => $record->isVerified())
                    ->authorize(fn (TenantExport $record) => static::isStaff() && $record->tenant_id === $this->tenant()->getTenantKey())
                    ->url(fn (TenantExport $record) => route('filament.admin.tenant-exports.download', ['export' => $record])),
            ]);
    }
}
