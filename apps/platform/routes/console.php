<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settles payments whose provider callback is late or missing.
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping()->onOneServer();

// Flags typed M-Pesa codes never confirmed and paid pushes no sale claims; one owner digest per half hour.
Schedule::command('payments:check-mpesa')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Resends eTIMS documents whose retry is due (30 s, 60 s, 120 s, then hourly).
Schedule::command('etims:retry')->everyMinute()->withoutOverlapping()->onOneServer();

// Suspended tenants: remind owners 7 days and 1 day before deletion, in office hours.
Schedule::command('tenants:suspension-reminders')
    ->dailyAt('09:00')
    ->timezone((string) config('vendapos.business_timezone'))
    ->onOneServer();

// Exports and deletes suspended tenants whose purge date has passed. At night,
// and only ever after the date the owner was given.
Schedule::command('tenants:purge')
    ->dailyAt('02:00')
    ->timezone((string) config('vendapos.business_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Keeps the trusted Cloudflare edge ranges current; a failed fetch keeps the previous list.
Schedule::command('network:refresh-cloudflare-ips')
    ->dailyAt('04:00')
    ->timezone((string) config('vendapos.business_timezone'))
    ->onOneServer();

// Deletes device tokens a day after their renewal grace window ends; until
// then an expired token can still be renewed, so it must be kept.
Schedule::command('sanctum:prune-expired', ['--hours' => ((int) config('vendapos.device_token_grace_days') + 1) * 24])
    ->dailyAt('03:30')
    ->timezone((string) config('vendapos.business_timezone'))
    ->onOneServer();
