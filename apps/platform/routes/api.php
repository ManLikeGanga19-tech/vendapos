<?php

declare(strict_types=1);

use App\Http\Api\V1\Controllers\ChangeStaffPin;
use App\Http\Api\V1\Controllers\PairDevice;
use App\Http\Api\V1\Controllers\RefreshDeviceToken;
use App\Http\Api\V1\Controllers\RenewDeviceToken;
use App\Http\Api\V1\Controllers\RequestPayment;
use App\Http\Api\V1\Controllers\ShowCatalogue;
use App\Http\Api\V1\Controllers\ShowDevice;
use App\Http\Api\V1\Controllers\ShowPayment;
use App\Http\Api\V1\Controllers\ShowTillStaff;
use App\Http\Api\V1\Controllers\SyncOrders;
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
        Route::get('staff', ShowTillStaff::class)
            ->middleware('ability:staff:read')
            ->name('api.v1.staff');
        Route::post('staff/{staffId}/pin', ChangeStaffPin::class)
            ->middleware(['ability:staff:pin', 'body.limit'])
            ->whereUuid('staffId')
            ->name('api.v1.staff.pin');
        Route::post('orders/sync', SyncOrders::class)
            ->middleware(['ability:orders:sync', 'body.limit'])
            ->name('api.v1.orders.sync');
        Route::post('payments', RequestPayment::class)
            ->middleware(['ability:payments:initiate', 'tenant.active', 'body.limit'])
            ->name('api.v1.payments.store');
        Route::get('payments/{uuid}', ShowPayment::class)
            ->middleware('ability:payments:initiate')
            ->whereUuid('uuid')
            ->name('api.v1.payments.show');
    });

/*
| Renewal of a device token that expired within the grace window. The token
| no longer authenticates, so this route is outside auth:sanctum; it checks
| the token and X-Device-ID itself and has its own strict limiter.
*/
Route::post('v1/device/token/renew', RenewDeviceToken::class)
    ->middleware('throttle.strict:token_renewal')
    ->name('api.v1.device.token.renew');

/*
| Pairing a new till with a one-time code from the Owner panel. The till has
| no token yet, so this is outside auth:sanctum, with its own strict limiter.
*/
Route::post('v1/device/pair', PairDevice::class)
    ->middleware(['throttle.strict:device_pairing', 'body.limit'])
    ->name('api.v1.device.pair');
