<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settles payments whose provider callback is late or missing.
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping()->onOneServer();

// Resends eTIMS documents whose retry is due (30 s, 60 s, 120 s, then hourly).
Schedule::command('etims:retry')->everyMinute()->withoutOverlapping()->onOneServer();

// Deletes device tokens a day after they expire. An expired token can no
// longer be used or refreshed, so this is only housekeeping.
Schedule::command('sanctum:prune-expired', ['--hours' => 24])
    ->dailyAt('03:30')
    ->timezone((string) config('vendapos.business_timezone'))
    ->onOneServer();
