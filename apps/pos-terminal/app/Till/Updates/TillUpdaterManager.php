<?php

declare(strict_types=1);

namespace App\Till\Updates;

use Native\Desktop\Drivers\Electron\Updater\Contracts\Updater;
use Native\Desktop\Drivers\Electron\Updater\UpdaterManager;

/** NativePHP's updater drivers, plus "web": a plain HTTPS folder on our own server. */
final class TillUpdaterManager extends UpdaterManager
{
    /** @param  array<string, mixed>  $config */
    protected function createWebDriver(array $config): Updater
    {
        return new WebUpdates($config);
    }
}
