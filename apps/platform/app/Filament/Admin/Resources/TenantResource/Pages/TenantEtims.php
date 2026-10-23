<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Etims\Actions\ListOpenSubmissions;
use App\Domain\Etims\Actions\RetrySubmissionNow;
use App\Domain\Tenancy\Actions\ListBranches;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/** A tenant's eTIMS documents still pending or failed (architecture §6.4), with "Retry now". */
class TenantEtims extends TenantDataPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    public static function getNavigationLabel(): string
    {
        return __('admin.tenant.etims');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.tenant.etims');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->inTenant(function () {
                $branches = app(ListBranches::class)->handle();

                return array_map(
                    fn (array $row) => [...$row, 'branch' => $branches[$row['branch_id']]['name'] ?? $row['branch_id']],
                    app(ListOpenSubmissions::class)->handle(),
                );
            }))
            ->emptyStateHeading(__('admin.etims.empty'))
            ->columns([
                TextColumn::make('order_id')->label(__('admin.etims.order'))->limit(8)->copyable(),
                TextColumn::make('branch')->label(__('admin.etims.branch')),
                TextColumn::make('document_type')->label(__('admin.etims.document'))
                    ->formatStateUsing(fn (string $state) => __("admin.etims.{$state}")),
                TextColumn::make('status')->label(__('admin.etims.status'))->badge()
                    ->formatStateUsing(fn (string $state) => __("admin.etims.{$state}"))
                    ->color(fn (string $state) => $state === 'failed' ? 'danger' : 'warning'),
                TextColumn::make('attempts')->label(__('admin.etims.attempts')),
                TextColumn::make('next_attempt_at')->label(__('admin.etims.next_attempt'))->since()->placeholder('—'),
                TextColumn::make('last_error')->label(__('admin.etims.last_error'))->limit(60)->placeholder('—'),
                TextColumn::make('created_at')->label(__('admin.etims.queued'))->dateTime(),
            ])
            ->recordActions([
                Action::make('retryNow')
                    ->label(__('admin.etims.retry_now'))
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.etims.retry_confirm'))
                    ->authorize(fn () => static::isStaff())
                    ->action(function (array $record) {
                        $tenantId = (string) $this->tenant()->getTenantKey();

                        try {
                            $this->inTenant(fn () => app(RetrySubmissionNow::class)->handle($tenantId, $record['id'], $this->staffEmail()));
                            $this->flushCachedTableRecords();
                        } catch (DomainException $e) {
                            Notification::make()->title(__('admin.errors.action_failed'))->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('admin.etims.retry_queued'))->success()->send();
                    }),
            ]);
    }
}
