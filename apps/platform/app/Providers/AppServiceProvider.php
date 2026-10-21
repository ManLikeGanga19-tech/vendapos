<?php

namespace App\Providers;

use App\Domain\Analytics\Jobs\UpdateSalesSummaries;
use App\Domain\Inventory\Jobs\DecrementStock;
use App\Domain\Inventory\Jobs\RestoreStock;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Tenancy\Models\Device;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        // Order side effects (architecture §6.1). The events fire after commit; each job carries the outbox event ID.
        Event::listen(OrderRecorded::class, function (OrderRecorded $event) {
            DecrementStock::dispatch($event->tenantId, $event->eventId);
            UpdateSalesSummaries::dispatch($event->tenantId, $event->eventId);
        });

        Event::listen(OrderVoided::class, function (OrderVoided $event) {
            RestoreStock::dispatch($event->tenantId, $event->eventId);
            UpdateSalesSummaries::dispatch($event->tenantId, $event->eventId);
        });
    }

    private function deviceOf(Request $request): ?Device
    {
        $user = $request->user('sanctum');

        return $user instanceof Device ? $user : null;
    }
}
