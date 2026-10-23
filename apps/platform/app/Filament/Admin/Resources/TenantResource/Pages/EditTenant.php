<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Domain\Tenancy\Actions\IssueOwnerSetupLink;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource;
use App\Filament\Admin\Resources\TenantResource\Widgets\TenantSalesByHour;
use App\Filament\Admin\Resources\TenantResource\Widgets\TenantToday;
use Filament\Actions\Action;
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
                ->requiresConfirmation()
                ->modalDescription(fn (Tenant $record) => __('admin.owner_login.confirm', ['name' => $record->owner_name]))
                ->action(fn (Tenant $record) => $this->issueOwnerLoginLink($record)),
        ];
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
