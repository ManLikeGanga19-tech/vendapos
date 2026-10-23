<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Owner\Auth\Login;
use App\Filament\Owner\Auth\SetUpAccount;
use App\Http\Middleware\EnsureSessionBelongsToTenant;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * The business owner's panel, served at the root of each tenant's own domain
 * (e.g. danamo.vendapos.com). Staff sign in with a username on the `tenant`
 * guard and must set up an authenticator app before they get in.
 */
class OwnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('owner')
            ->path('')
            ->authGuard('tenant')
            // The owner's own business, not ours, heads every page.
            ->brandName(fn (): string => (string) (tenant('name') ?? 'VendaPOS'))
            ->login(Login::class)
            ->multiFactorAuthentication(
                AppAuthentication::make()->brandName('VendaPOS')->recoverable(),
                isRequired: true,
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.square-corners'))
            ->routes(fn () => Route::get('setup/{token}', SetUpAccount::class)->name('auth.setup'))
            ->discoverResources(in: app_path('Filament/Owner/Resources'), for: 'App\Filament\Owner\Resources')
            ->discoverPages(in: app_path('Filament/Owner/Pages'), for: 'App\Filament\Owner\Pages')
            ->discoverWidgets(in: app_path('Filament/Owner/Widgets'), for: 'App\Filament\Owner\Widgets')
            ->middleware([
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
            // Persistent, so Livewire's own update requests run in the tenant
            // too. The tenancy middleware is sorted ahead of StartSession by
            // TenancyServiceProvider; the session check stays after it.
            ->middleware([
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
                EnsureSessionBelongsToTenant::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
