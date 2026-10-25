<?php

declare(strict_types=1);

use App\Jobs\SyncTillJob;
use Illuminate\Support\Facades\Schedule;

// Watchdog: restarts the self-queuing sync chain if it stopped.
Schedule::job(new SyncTillJob)->everyMinute();
