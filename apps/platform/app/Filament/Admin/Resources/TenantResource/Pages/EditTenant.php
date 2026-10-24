<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Tenancy\Actions\IssueOwnerSetupLink;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Actions\SuspendTenant;
use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource;
use App\Filament\Admin\Resources\TenantResource\Widgets\TenantSalesByHour;
use App\Filament\Admin\Resources\TenantResource\Widgets\TenantToday;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TenantToday::class,
            TenantSalesByHour::class,
        ];
    }

    /**
     * No delete here: deleting a tenant drops its whole schema. Tenants are
     * suspended first and purged later by a separate, notified process.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ownerLoginLink')
                ->label(__('admin.owner_login.action'))
                ->icon('heroicon-o-key')
                ->visible(fn (Tenant $record) => $record->isActive())
                ->requiresConfirmation()
                ->modalDescription(fn (Tenant $record) => __('admin.owner_login.confirm', ['name' => $record->owner_name]))
                ->action(fn (Tenant $record) => $this->issueOwnerLoginLink($record)),
            Action::make('suspend')
                ->label(__('admin.suspension.suspend'))
                ->icon('heroicon-o-pause-circle')
                ->color('danger')
                ->visible(fn (Tenant $record) => $record->isActive())
                ->modalHeading(fn (Tenant $record) => __('admin.suspension.suspend_heading', ['name' => $record->name]))
                ->modalDescription(__('admin.suspension.suspend_description', ['days' => (int) config('vendapos.suspension.purge_after_days')]))
                ->modalSubmitActionLabel(__('admin.suspension.suspend'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('admin.suspension.reason'))
                        ->helperText(__('admin.suspension.reason_help'))
                        ->required()
                        ->maxLength(500),
                ])
                ->action(fn (Tenant $record, array $data) => $this->changeStatus(
                    fn () => app(SuspendTenant::class)->handle($record, (string) $data['reason'], $this->admin()),
                    __('admin.suspension.suspended'),
                )),
            Action::make('reactivate')
                ->label(__('admin.suspension.reactivate'))
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->visible(fn (Tenant $record) => $record->isSuspended())
                ->requiresConfirmation()
                ->modalDescription(__('admin.suspension.reactivate_description'))
                ->action(fn (Tenant $record) => $this->changeStatus(
                    fn () => app(ReactivateTenant::class)->handle($record, $this->admin()),
                    __('admin.suspension.reactivated'),
                )),
        ];
    }

    public function getSubheading(): ?string
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();

        if (! $tenant->isSuspended()) {
            return null;
        }

        return __('admin.suspension.subheading', [
            'since' => $tenant->suspended_at?->timezone((string) config('vendapos.business_timezone'))->format('j M Y H:i'),
            'purge' => $tenant->purge_after?->timezone((string) config('vendapos.business_timezone'))->format('j M Y'),
            'reason' => $tenant->suspension_reason,
        ]);
    }

    /** @param  callable(): Tenant  $change */
    private function changeStatus(callable $change, string $done): void
    {
        try {
            $change();
            Notification::make()->title($done)->success()->send();
        } catch (TenantStatusConflict) {
            // Someone else changed it first; show the tenant as it now is.
            Notification::make()->title(__('admin.suspension.conflict'))->warning()->send();
        }

        $this->getRecord()->refresh();
    }

    private function admin(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private function issueOwnerLoginLink(Tenant $tenant): void
    {
        $domain = $tenant->domains()->value('domain');

        if (! is_string($domain)) {
            Notification::make()->title(__('admin.owner_login.no_domain'))->danger()->send();

            return;
        }

        $issuedBy = (string) auth()->user()?->getAttribute('email');
        $token = $tenant->run(fn () => app(IssueOwnerSetupLink::class)->handle((string) $tenant->owner_name, $issuedBy));

        Notification::make()
            ->title(__('admin.owner_login.created', ['name' => $tenant->owner_name, 'hours' => IssueOwnerSetupLink::VALID_HOURS]))
            ->body($this->tenantUrl($domain, "/setup/{$token}").'  '.__('admin.owner_login.copy_hint'))
            ->success()
            ->persistent()
            ->send();
    }

    /** Same scheme and port as this app (http://…:8000 locally, https in production), on the tenant's domain. */
    private function tenantUrl(string $domain, string $path): string
    {
        $app = parse_url((string) config('app.url'));
        $port = isset($app['port']) ? ':'.$app['port'] : '';

        return ($app['scheme'] ?? 'https')."://{$domain}{$port}{$path}";
    }
}
