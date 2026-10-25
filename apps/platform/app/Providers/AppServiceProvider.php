<?php

namespace App\Providers;

use App\Domain\Analytics\Jobs\UpdateSalesSummaries;
use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Catalogue\Observers\TellTillsMenuChanged;
use App\Domain\Etims\Contracts\EtimsClient;
use App\Domain\Etims\Jobs\SubmitCreditNoteToEtims;
use App\Domain\Etims\Jobs\SubmitSaleToEtims;
use App\Domain\Inventory\Jobs\DecrementStock;
use App\Domain\Inventory\Jobs\RestoreStock;
use App\Domain\Orders\Events\OrderRecorded;
use App\Domain\Orders\Events\OrderVoided;
use App\Domain\Payments\Events\PaymentRequested;
use App\Domain\Payments\Jobs\FlagPaymentsForCancelledOrder;
use App\Domain\Payments\Jobs\InitiatePayment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Observers\TellTillsSetupChanged;
use App\Domain\Tenancy\Observers\TellTillsStaffChanged;
use App\Infrastructure\Notifications\RecordNotificationDelivery;
use App\Infrastructure\Sms\SmsClient;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EtimsClient::class, function () {
            /** @var array<string, class-string<EtimsClient>> $clients */
            $clients = config('vendapos.etims.clients');

            return $this->app->make($clients[(string) config('vendapos.etims.client')]);
        });

        // A singleton, so the fake client keeps what it sent for the whole request or job.
        $this->app->singleton(SmsClient::class, function () {
            /** @var array<string, class-string<SmsClient>> $clients */
            $clients = config('vendapos.sms.clients');

            return $this->app->make($clients[(string) config('vendapos.sms.client')]);
        });
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

        // Stored in UTC, shown in the business timezone in every panel (CLAUDE.md §4).
        FilamentTimezone::set((string) config('vendapos.business_timezone'));

        // Keyed on the authenticated device, so they run after auth:sanctum and device.bound.
        RateLimiter::for('device', fn (Request $request) => Limit::perMinute((int) config('vendapos.rate_limits.device'))
            ->by('device:'.$this->deviceOf($request)?->id));

        RateLimiter::for('payment-callbacks', fn (Request $request) => Limit::perMinute((int) config('vendapos.payments.callback_rate_limit'))
            ->by('callback:'.$request->ip()));

        RateLimiter::for('tenant', fn (Request $request) => Limit::perMinute((int) config('vendapos.rate_limits.tenant'))
            ->by('tenant:'.$this->deviceOf($request)?->tenant_id));

        // Order side effects (architecture §6.1). The events fire after commit; each job carries the outbox event ID.
        Event::listen(OrderRecorded::class, function (OrderRecorded $event) {
            DecrementStock::dispatch($event->tenantId, $event->eventId);
            UpdateSalesSummaries::dispatch($event->tenantId, $event->eventId);
            SubmitSaleToEtims::dispatch($event->tenantId, $event->eventId);
            FlagPaymentsForCancelledOrder::dispatch($event->tenantId, $event->eventId);
        });

        Event::listen(OrderVoided::class, function (OrderVoided $event) {
            RestoreStock::dispatch($event->tenantId, $event->eventId);
            UpdateSalesSummaries::dispatch($event->tenantId, $event->eventId);
            SubmitCreditNoteToEtims::dispatch($event->tenantId, $event->eventId);
        });

        Event::listen(NotificationSent::class, RecordNotificationDelivery::class);

        // Real-time: what tills hold changed, so they sync now instead of at their next minute.
        Item::observe(TellTillsMenuChanged::class);
        Category::observe(TellTillsMenuChanged::class);
        Staff::observe(TellTillsStaffChanged::class);
        Device::observe(TellTillsSetupChanged::class);
        Branch::observe(TellTillsSetupChanged::class);
        Tenant::observe(TellTillsSetupChanged::class);

        Event::listen(PaymentRequested::class, fn (PaymentRequested $event) => InitiatePayment::dispatch($event->tenantId, $event->eventId));

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof Staff) {
                $event->user->forceFill(['last_login_at' => now()])->save();
            }
        });

        // Tenant-only routes (the Owner panel at /) on a VendaPOS domain: send
        // people to the Admin panel instead of a bare 404.
        PreventAccessFromCentralDomains::$abortRequest = fn (Request $request) => $request->isMethod('GET')
            ? redirect('/admin')
            : abort(404);
    }

    private function deviceOf(Request $request): ?Device
    {
        $user = $request->user('sanctum');

        return $user instanceof Device ? $user : null;
    }
}
