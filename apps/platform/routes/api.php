<?php

declare(strict_types=1);

use App\Http\Api\V1\Controllers\RefreshDeviceToken;
use App\Http\Api\V1\Controllers\ShowCatalogue;
use App\Http\Api\V1\Controllers\ShowDevice;
use Illuminate\Support\Facades\Route;

/*
| Terminal and mobile API. Every route authenticates as a device, is bound to
| it by X-Device-ID, runs inside that device's tenant, and is rate limited
| per device and per tenant.
*/
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'device.bound', 'throttle:device', 'throttle:tenant'])
    ->group(function () {
        Route::get('device', ShowDevice::class)->name('api.v1.device');
        Route::post('device/token', RefreshDeviceToken::class)->name('api.v1.device.token');
        Route::get('catalogue', ShowCatalogue::class)->name('api.v1.catalogue');
    });
