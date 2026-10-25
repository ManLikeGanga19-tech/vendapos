<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\SyncTillJob;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        $window = Window::open()
            ->title('VendaPOS')
            ->width(1280)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(700)
            ->preventLeaveDomain()
            ->suppressNewWindows();

        if (config('terminal.kiosk')) {
            // A counter machine: full screen, nothing to close, no developer tools.
            // Development keeps the menu, whose Ctrl+R reloads the window after a change.
            $window->kiosk()
                ->hideMenu()
                ->closable(false)
                ->minimizable(false)
                ->hideDevTools();
        }

        // Start the sync chain now rather than waiting for the minute scheduler.
        SyncTillJob::dispatch();
    }

    /**
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [];
    }
}
