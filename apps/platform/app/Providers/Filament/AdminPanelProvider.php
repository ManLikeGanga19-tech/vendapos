<?php

namespace App\Providers\Filament;

use App\Filament\TwoFactorRequirement;
use App\Http\Controllers\Admin\DownloadTenantExport;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\UseEnglish;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->multiFactorAuthentication(
                AppAuthentication::make()->brandName('VendaPOS Admin')->recoverable(),
                isRequired: TwoFactorRequirement::applies(),
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.square-corners'))
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EnsureCentralDomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent, so Livewire's own update requests are English too.
            ->middleware([UseEnglish::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ])
            // Behind the panel's sign-in and platform-admin check. Filament adds the
            // "authenticator required" check only to its own pages, so add it here.
            ->authenticatedRoutes(fn () => Route::get('tenant-exports/{export}/download', DownloadTenantExport::class)
                ->middleware(TwoFactorRequirement::middleware($panel->getMultiFactorAuthenticationRequiredMiddleware()))
                ->name('tenant-exports.download'));
    }
}
