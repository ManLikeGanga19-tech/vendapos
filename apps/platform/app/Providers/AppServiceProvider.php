<?php

namespace App\Providers;

use App\Domain\Tenancy\Models\Device;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations'),
            database_path('migrations/central'),
        ]);

        // Keyed on the authenticated device, so they run after auth:sanctum and device.bound.
        RateLimiter::for('device', fn (Request $request) => Limit::perMinute((int) config('vendapos.rate_limits.device'))
            ->by('device:'.$this->deviceOf($request)?->id));

        RateLimiter::for('tenant', fn (Request $request) => Limit::perMinute((int) config('vendapos.rate_limits.tenant'))
            ->by('tenant:'.$this->deviceOf($request)?->tenant_id));
    }

    private function deviceOf(Request $request): ?Device
    {
        $user = $request->user('sanctum');

        return $user instanceof Device ? $user : null;
    }
}
